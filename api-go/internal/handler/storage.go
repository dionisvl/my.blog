package handler

import (
	"net/http"
	"os"
	"regexp"
)

type noListFS struct {
	fs http.FileSystem
}

func (n *noListFS) Open(name string) (http.File, error) {
	f, err := n.fs.Open(name)
	if err != nil {
		return nil, err
	}

	stat, err := f.Stat()
	if err != nil {
		f.Close()
		return nil, err
	}

	if stat.IsDir() {
		f.Close()
		return nil, os.ErrNotExist
	}

	return f, nil
}

type cacheHeaderWriter struct {
	http.ResponseWriter
	cacheControl  string
	headerWritten bool
}

func (c *cacheHeaderWriter) WriteHeader(statusCode int) {
	if !c.headerWritten && statusCode == http.StatusOK {
		c.ResponseWriter.Header().Set("Cache-Control", c.cacheControl)
	}
	c.headerWritten = true
	c.ResponseWriter.WriteHeader(statusCode)
}

func (c *cacheHeaderWriter) Write(b []byte) (int, error) {
	if !c.headerWritten {
		c.WriteHeader(http.StatusOK)
	}
	return c.ResponseWriter.Write(b)
}

// hashedMediaName matches names with the content hash in them, so the bytes behind the URL never change.
var hashedMediaName = regexp.MustCompile(`_[0-9a-f]{16}\.webp$`)

func cacheControlFor(path string) string {
	if hashedMediaName.MatchString(path) {
		return "public, max-age=31536000, immutable"
	}

	return "public, max-age=86400"
}

func newCacheHeaderMiddleware(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		next.ServeHTTP(&cacheHeaderWriter{ResponseWriter: w, cacheControl: cacheControlFor(r.URL.Path)}, r)
	})
}

func NewStorageHandler(storageDir string) http.Handler {
	fs := &noListFS{fs: http.Dir(storageDir)}
	fileServer := http.FileServer(fs)
	return newCacheHeaderMiddleware(http.StripPrefix("/storage", fileServer))
}
