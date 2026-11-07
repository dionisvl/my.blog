package model_test

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"os"
	"path/filepath"
	"regexp"
	"sort"
	"strings"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"

	"api-go/internal/model"
)

// The article block contract lives in api-symfony/contracts/article-blocks/v1 (source of truth) and is
// vendored into frontend-astro. Go only transports the document, so these tests guard against drift:
// the manifest hashes, the vendored copy, and the shape of every golden fixture.
const (
	sourceDir   = "../../../api-symfony/contracts/article-blocks/v1"
	vendoredDir = "../../../frontend-astro/src/contracts/article-blocks/v1"
)

var manifestLine = regexp.MustCompile(`^([0-9a-f]{64})  (\S+)$`)

var expectedBlockTypes = []string{
	"richText", "comparisonTable", "productCard", "faq", "cta", "cardSelector", "sources", "sponsoredDisclosure", "customCode",
}

func readManifest(t *testing.T, dir string) (map[string]string, []string) {
	t.Helper()

	raw, err := os.ReadFile(filepath.Join(dir, "MANIFEST.sha256"))
	require.NoError(t, err)

	entries := map[string]string{}
	var order []string
	for _, line := range strings.Split(strings.TrimSpace(string(raw)), "\n") {
		match := manifestLine.FindStringSubmatch(line)
		require.NotNil(t, match, "malformed manifest line %q", line)
		entries[match[2]] = match[1]
		order = append(order, match[2])
	}
	return entries, order
}

func sha256File(t *testing.T, path string) string {
	t.Helper()

	raw, err := os.ReadFile(path)
	require.NoError(t, err)
	sum := sha256.Sum256(raw)
	return hex.EncodeToString(sum[:])
}

func TestContract_ManifestMatchesFiles(t *testing.T) {
	entries, order := readManifest(t, sourceDir)

	var onDisk []string
	onDisk = append(onDisk, "schema.json")
	for _, kind := range []string{"valid", "invalid"} {
		files, err := filepath.Glob(filepath.Join(sourceDir, "fixtures", kind, "*.json"))
		require.NoError(t, err)
		for _, f := range files {
			onDisk = append(onDisk, "fixtures/"+kind+"/"+filepath.Base(f))
		}
	}
	sort.Strings(onDisk)

	assert.Equal(t, onDisk, order, "MANIFEST.sha256 must list exactly the contract files, sorted")
	for relative, want := range entries {
		assert.Equal(t, want, sha256File(t, filepath.Join(sourceDir, relative)), relative)
	}
}

func TestContract_VendoredFrontendCopyIsIdentical(t *testing.T) {
	if _, err := os.Stat(vendoredDir); os.IsNotExist(err) {
		t.Skip("frontend-astro is not part of this checkout")
	}

	entries, _ := readManifest(t, sourceDir)

	assert.Equal(t, sha256File(t, filepath.Join(sourceDir, "MANIFEST.sha256")), sha256File(t, filepath.Join(vendoredDir, "MANIFEST.sha256")))
	for relative, want := range entries {
		assert.Equal(t, want, sha256File(t, filepath.Join(vendoredDir, relative)), "vendored "+relative)
	}
}

func TestContract_SchemaMatchesGoConstants(t *testing.T) {
	raw, err := os.ReadFile(filepath.Join(sourceDir, "schema.json"))
	require.NoError(t, err)

	var schema struct {
		Properties struct {
			SchemaVersion struct {
				Const int `json:"const"`
			} `json:"schema_version"`
			Locale struct {
				Enum []string `json:"enum"`
			} `json:"locale"`
		} `json:"properties"`
		Defs struct {
			Block struct {
				Properties struct {
					Type struct {
						Enum []string `json:"enum"`
					} `json:"type"`
				} `json:"properties"`
			} `json:"block"`
		} `json:"$defs"`
	}
	require.NoError(t, json.Unmarshal(raw, &schema))

	assert.Equal(t, model.ContractVersion, schema.Properties.SchemaVersion.Const)
	assert.Equal(t, []string{model.LocaleRU, model.LocaleEN}, schema.Properties.Locale.Enum)
	assert.Equal(t, expectedBlockTypes, schema.Defs.Block.Properties.Type.Enum)
}

type fixtureBlock struct {
	ID    string          `json:"id"`
	Type  string          `json:"type"`
	Props json.RawMessage `json:"props"`
}

type fixtureDocument struct {
	SchemaVersion int            `json:"schema_version"`
	Locale        string         `json:"locale"`
	VerifiedAt    string         `json:"verified_at"`
	Blocks        []fixtureBlock `json:"blocks"`
}

func TestContract_ValidFixturesAreWellFormedAndPassThroughUnchanged(t *testing.T) {
	files, err := filepath.Glob(filepath.Join(sourceDir, "fixtures", "valid", "*.json"))
	require.NoError(t, err)
	require.NotEmpty(t, files)

	known := map[string]bool{}
	for _, blockType := range expectedBlockTypes {
		known[blockType] = true
	}

	for _, file := range files {
		t.Run(filepath.Base(file), func(t *testing.T) {
			raw, err := os.ReadFile(file)
			require.NoError(t, err)

			var doc fixtureDocument
			require.NoError(t, json.Unmarshal(raw, &doc))

			assert.Equal(t, model.ContractVersion, doc.SchemaVersion)
			assert.True(t, model.IsSupportedLocale(doc.Locale))
			require.NotEmpty(t, doc.Blocks)

			seen := map[string]bool{}
			for _, block := range doc.Blocks {
				assert.True(t, known[block.Type], "unknown block type %q", block.Type)
				assert.False(t, seen[block.ID], "duplicate block id %q", block.ID)
				seen[block.ID] = true
				assert.True(t, json.Valid(block.Props))
			}

			// Go serves the document as an opaque value: marshalling the API model must not alter it.
			published := model.PublishedLocale{Locale: doc.Locale, Document: json.RawMessage(raw)}
			encoded, err := json.Marshal(published)
			require.NoError(t, err)

			var envelope struct {
				Document json.RawMessage `json:"document"`
			}
			require.NoError(t, json.Unmarshal(encoded, &envelope))
			assert.JSONEq(t, string(raw), string(envelope.Document))
		})
	}
}

func TestContract_RuAndEnFixturesHaveTheSameStructure(t *testing.T) {
	shape := func(name string) []string {
		raw, err := os.ReadFile(filepath.Join(sourceDir, "fixtures", "valid", name))
		require.NoError(t, err)

		var doc fixtureDocument
		require.NoError(t, json.Unmarshal(raw, &doc))

		var out []string
		for _, block := range doc.Blocks {
			out = append(out, block.ID+":"+block.Type)
		}
		return out
	}

	assert.Equal(t, shape("cards-ru.json"), shape("cards-en.json"))
}

func TestContract_InvalidFixturesDeclareTheirExpectation(t *testing.T) {
	files, err := filepath.Glob(filepath.Join(sourceDir, "fixtures", "invalid", "*.json"))
	require.NoError(t, err)
	require.NotEmpty(t, files)

	for _, file := range files {
		raw, err := os.ReadFile(file)
		require.NoError(t, err)

		var fixture struct {
			Description string          `json:"description"`
			Mode        string          `json:"mode"`
			ExpectCodes []string        `json:"expect_codes"`
			Document    json.RawMessage `json:"document"`
		}
		require.NoError(t, json.Unmarshal(raw, &fixture), file)

		base := filepath.Base(file)
		assert.NotEmpty(t, fixture.Description, base)
		assert.Contains(t, []string{"draft", "publication"}, fixture.Mode, base)
		assert.NotEmpty(t, fixture.ExpectCodes, base)
		assert.True(t, json.Valid(fixture.Document), base)
	}
}
