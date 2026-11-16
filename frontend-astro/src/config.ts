import {
  PUBLIC_SITE_URL,
  PUBLIC_SITE_TITLE,
  PUBLIC_SITE_DESC,
  PUBLIC_SITE_AUTHOR,
} from "astro:env/client";

const siteUrl = PUBLIC_SITE_URL;
const siteTitle = PUBLIC_SITE_TITLE;

export const SITE = {
  website: siteUrl.endsWith("/") ? siteUrl : `${siteUrl}/`,
  author: PUBLIC_SITE_AUTHOR,
  profile: siteUrl.endsWith("/") ? siteUrl : `${siteUrl}/`,
  desc: PUBLIC_SITE_DESC,
  title: siteTitle,
  lang: "en",
} as const;
