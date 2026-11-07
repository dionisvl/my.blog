import type {
  ArticleLocale,
  LocaleLink,
  RouteMode,
} from "../contracts/article-blocks/v1/types";

export interface User {
  id: number;
  name: string;
  created_at: string;
  updated_at: string;
}

export interface Tag {
  id: number;
  title: string;
  slug: string;
  created_at: string;
  updated_at: string;
}

export interface Category {
  id: number;
  title: string;
  slug: string;
  preview_text?: string;
  detail_text?: string;
  created_at: string;
  updated_at: string;
}

export interface CategoryWithCount extends Category {
  posts_count: number;
}

export interface Comment {
  id: number;
  author_name: string;
  text: string;
  created_at: string;
  updated_at: string;
}

export interface Aphorism {
  id: number;
  text: string;
}

export interface Post {
  id: number;
  title: string;
  slug: string;
  content?: string;
  description?: string;
  is_featured: boolean;
  views_count: number;
  likes_count: number;
  image_url: string;
  created_at: string;
  updated_at: string;
  author?: User;
  category?: Category;
  tags?: Tag[];
  comments?: Comment[];
  // Additive fields (modular articles); absent or "legacy_canonical" for existing posts.
  route_mode?: RouteMode;
  original_locale?: ArticleLocale;
  primary_locale?: ArticleLocale;
  /** Published locales of a modular article (card links). */
  locales?: LocaleLink[];
}

export interface Pagination {
  current_page: number;
  total_pages: number;
  total: number;
  per_page: number;
  has_prev: boolean;
  has_next: boolean;
  prev_page: number;
  next_page: number;
}

export interface HomeResponse {
  posts: Post[];
  featured_posts: Post[];
  recent_posts: Post[];
  categories: CategoryWithCount[];
  pagination: Pagination;
}

export interface PostResponse {
  post: Post;
  featured_posts: Post[];
  recent_posts: Post[];
  categories: CategoryWithCount[];
  aphorism?: Aphorism;
}

export interface TagResponse {
  posts: Post[];
  tag: Tag;
  pagination: Pagination;
}

export interface CategoryResponse {
  posts: Post[];
  category: Category;
  pagination: Pagination;
}

export interface SearchResponse {
  posts: Post[];
  query: string;
}

export interface ContactsResponse {
  email: string;
  phone?: string;
  website?: string;
}
