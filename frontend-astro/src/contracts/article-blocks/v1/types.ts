/**
 * TypeScript view of the modular article contract, schema_version 1.
 *
 * `schema.json` (vendored byte-for-byte from api-symfony/contracts/article-blocks/v1, see
 * MANIFEST.sha256) is the source of truth; this file is maintained by hand next to it.
 * Story 2 adds a runner that validates the fixtures against both.
 *
 * Security notes for renderers:
 *  - every string is plain text and must be escaped, except `html` / `answerHtml`, which are
 *    server-sanitized (allow-list) HTML and may be passed to `set:html`, and the `customCode` block,
 *    whose `html`, `css` and `js` are owner-authored raw code rendered as-is in the first release;
 *  - URLs are http(s) or site-relative; `sponsored: true` links need rel="sponsored".
 */

export const ARTICLE_SCHEMA_VERSION = 1 as const;

export const ARTICLE_LOCALES = ["ru", "en"] as const;
export type ArticleLocale = (typeof ARTICLE_LOCALES)[number];

export const ARTICLE_BLOCK_TYPES = [
  "richText",
  "comparisonTable",
  "productCard",
  "faq",
  "cta",
  "cardSelector",
  "sources",
  "sponsoredDisclosure",
  "customCode",
] as const;
export type ArticleBlockType = (typeof ARTICLE_BLOCK_TYPES)[number];

export type Rating = "good" | "mixed" | "bad" | "neutral";
export type CtaVariant = "primary" | "ghost";

/** `YYYY-MM-DD` */
export type DateString = string;

export interface Cta {
  label: string;
  url: string;
  variant: CtaVariant;
  sponsored: boolean;
}

/** Raw, unsanitized owner-authored code (first release only). */
export interface CustomCodeProps {
  html?: string;
  css?: string;
  js?: string;
}

export interface RichTextProps {
  /** Sanitized HTML (p, br, strong, b, em, i, u, ul, ol, li, a, h2-h4, blockquote, code, pre). */
  html: string;
  variant?: "default" | "lead" | "callout";
}

export interface ComparisonColumn {
  id: string;
  name: string;
  subtitle?: string;
  link?: { url: string; sponsored: boolean };
}

export interface ComparisonCell {
  rating?: Rating;
  value: string;
  note?: string;
}

export interface ComparisonRow {
  id: string;
  label: string;
  /** Exactly one cell per column, in column order. */
  cells: ComparisonCell[];
}

export interface ComparisonTableProps {
  caption: string;
  criteriaLabel: string;
  legend?: { rating: Rating; label: string }[];
  columns: ComparisonColumn[];
  rows: ComparisonRow[];
  ctaRow?: {
    label: string;
    items: (Cta & { columnId: string })[];
  };
}

export interface ProductCardProps {
  title: string;
  badge?: string;
  tag?: string;
  summary?: string;
  bullets?: string[];
  rating?: Rating;
  cta?: Cta;
  secondaryCta?: Cta;
  note?: string;
}

export interface FaqProps {
  heading: string;
  items: { id: string; question: string; answerHtml: string }[];
}

export interface CtaProps extends Cta {
  title?: string;
  text?: string;
  /** Render as the sticky bar on small screens. */
  sticky?: boolean;
}

export interface CardSelectorProps {
  heading: string;
  groupLabel: string;
  /** Id of the comparisonTable block this selector drives. */
  targetBlockId: string;
  defaultOptionId: string;
  options: { id: string; label: string; columnId: string }[];
  /** Every string rendered dynamically; placeholders: {name}, {cta}. */
  labels: {
    selectedTable: string;
    stickyCaption: string;
    ctaAria: string;
    mobileHint: string;
  };
}

export interface SourcesProps {
  heading: string;
  items: {
    id: string;
    label: string;
    url: string;
    sponsored?: boolean;
    publisher?: string;
    accessed_at?: DateString;
  }[];
}

export interface SponsoredDisclosureProps {
  text: string;
  learnMoreLabel?: string;
  learnMoreUrl?: string;
}

interface BlockBase<TType extends ArticleBlockType, TProps> {
  id: string;
  type: TType;
  props: TProps;
}

export type ArticleBlock =
  | BlockBase<"richText", RichTextProps>
  | BlockBase<"comparisonTable", ComparisonTableProps>
  | BlockBase<"productCard", ProductCardProps>
  | BlockBase<"faq", FaqProps>
  | BlockBase<"cta", CtaProps>
  | BlockBase<"cardSelector", CardSelectorProps>
  | BlockBase<"sources", SourcesProps>
  | BlockBase<"sponsoredDisclosure", SponsoredDisclosureProps>
  | BlockBase<"customCode", CustomCodeProps>;

export interface ArticleDocument {
  schema_version: typeof ARTICLE_SCHEMA_VERSION;
  locale: ArticleLocale;
  /** Required when the document contains sponsored links. */
  verified_at?: DateString;
  blocks: ArticleBlock[];
}

// --- Go API responses (api-go; contract_version 1) -------------------------------------------------

export const ROUTE_MODE_LEGACY = "legacy_canonical" as const;
export const ROUTE_MODE_NEUTRAL = "neutral" as const;
export type RouteMode = typeof ROUTE_MODE_LEGACY | typeof ROUTE_MODE_NEUTRAL;

/** A published modular locale, or the original/published translation of a classified legacy post. */
export interface LocaleLink {
  locale: ArticleLocale;
  url: string;
  title: string;
  published_at: string;
}

/** GET /api/post/{slug}/resolve -- side-effect free (no view count, no cookie), `Cache-Control: private, no-store`. */
export interface ArticleResolution {
  contract_version: 1;
  slug: string;
  route_mode: RouteMode;
  /** Legacy posts only; null until editorially classified (then keep legacy output). */
  original_locale: ArticleLocale | null;
  /** Modular articles only. */
  primary_locale: ArticleLocale | null;
  /** Legacy posts: `/post/{slug}`; modular articles: null (use `locales[].url`). */
  canonical_path: string | null;
  /** Modular: published locales; classified legacy: original path first, then published translations. */
  locales: LocaleLink[];
  /** "en" only when both locales are published; null otherwise. Never the neutral URL. */
  x_default_locale: ArticleLocale | null;
}

/** The published content of one locale. */
export interface PublishedLocale {
  locale: ArticleLocale;
  schema_version: number;
  published_at: string;
  /** Plain text. */
  title: string;
  /** Plain text. */
  summary: string;
  meta_title: string | null;
  meta_description: string | null;
  document: ArticleDocument;
}
