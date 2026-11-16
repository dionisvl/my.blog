ARG NODE_VERSION=26.13.1
ARG PNPM_VERSION=12.21.0
FROM node:${NODE_VERSION}-alpine AS base

WORKDIR /app

ARG PNPM_VERSION
RUN npm install -g pnpm@${PNPM_VERSION}

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./

FROM base AS deps
RUN --mount=type=cache,target=/pnpm/store,sharing=locked \
    pnpm install --frozen-lockfile --store-dir /pnpm/store

FROM base AS prod-deps
RUN --mount=type=cache,target=/pnpm/store,sharing=locked \
    pnpm install --prod --frozen-lockfile --store-dir /pnpm/store

FROM deps AS source

ARG PUBLIC_SITE_URL=https://web3main.pro
ARG PUBLIC_SITE_TITLE=web3main.pro
ARG PUBLIC_SITE_AUTHOR="Web3main LLC"
ARG PUBLIC_SITE_DESC="Stay updated with the latest trends in Web3 development, software engineering tips, and best coding practices for modern applications."
ARG PUBLIC_GOOGLE_SITE_VERIFICATION=""

ENV PUBLIC_SITE_URL=${PUBLIC_SITE_URL} \
    PUBLIC_SITE_TITLE=${PUBLIC_SITE_TITLE} \
    PUBLIC_SITE_AUTHOR=${PUBLIC_SITE_AUTHOR} \
    PUBLIC_SITE_DESC=${PUBLIC_SITE_DESC} \
    PUBLIC_GOOGLE_SITE_VERIFICATION=${PUBLIC_GOOGLE_SITE_VERIFICATION} \
    ASTRO_TELEMETRY_DISABLED=1

COPY . .

# Type checking runs in CI (--target check) instead of slowing down every image build.
FROM source AS check
RUN pnpm exec astro check

FROM source AS build
RUN pnpm exec astro build

FROM node:${NODE_VERSION}-alpine AS prod

# The runtime only needs node; drop npm/corepack so their bundled deps are not shipped
RUN rm -rf /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/corepack /usr/local/bin/npm /usr/local/bin/npx /usr/local/bin/corepack

WORKDIR /app

ENV NODE_ENV=production \
    HOST=0.0.0.0 \
    PORT=4321

COPY --from=build /app/dist ./dist
COPY --from=build /app/package.json ./package.json
COPY --from=prod-deps /app/node_modules ./node_modules

EXPOSE 4321

CMD ["node", "dist/server/entry.mjs"]
