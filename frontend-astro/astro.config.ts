import { defineConfig, envField } from "astro/config";
import node from "@astrojs/node";
import tailwindcss from "@tailwindcss/vite";
import {
  transformerNotationDiff,
  transformerNotationHighlight,
  transformerNotationWordHighlight,
} from "@shikijs/transformers";

const siteUrl = process.env.PUBLIC_SITE_URL ?? "http://phpqas.local";
const allowedHost = new URL(siteUrl).hostname;

// https://astro.build/config
export default defineConfig({
  output: "server",
  adapter: node({ mode: "standalone" }),
  integrations: [],
  // Without this Astro ignores the Host header and every URL reads as "localhost".
  security: {
    allowedDomains: [{ hostname: allowedHost }, { hostname: `www.${allowedHost}` }],
  },
  markdown: {
    shikiConfig: {
      themes: { light: "min-light", dark: "github-dark-default" },
      defaultColor: false,
      wrap: false,
      transformers: [
        transformerNotationHighlight(),
        transformerNotationWordHighlight(),
        transformerNotationDiff({ matchAlgorithm: "v3" }),
      ],
    },
  },
  vite: {
    plugins: [tailwindcss()],
    server: {
      allowedHosts: [allowedHost],
    },
  },
  env: {
    schema: {
      API_URL: envField.string({ context: "server", access: "secret" }),
      API_KEY: envField.string({ context: "server", access: "secret" }),
      PUBLIC_SITE_URL: envField.string({
        context: "client",
        access: "public",
      }),
      PUBLIC_SITE_TITLE: envField.string({
        context: "client",
        access: "public",
      }),
      PUBLIC_SITE_AUTHOR: envField.string({
        context: "client",
        access: "public",
      }),
      PUBLIC_SITE_DESC: envField.string({
        context: "client",
        access: "public",
      }),
      PUBLIC_GOOGLE_SITE_VERIFICATION: envField.string({
        access: "public",
        context: "client",
        optional: true,
      }),
    },
  },
});