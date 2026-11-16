import type { APIRoute } from "astro";
import { API_KEY, API_URL } from "astro:env/server";

export const prerender = false;

export const POST: APIRoute = async ({ params, request }) => {
  const postId = params.postId;

  // Read body (may be empty) — forward as-is or default to empty object
  let bodyText = "";
  try {
    bodyText = await request.text();
  } catch {
    bodyText = "";
  }

  const upstream = await fetch(
    `${API_URL}/api/postlike/${encodeURIComponent(postId ?? "")}`,
    {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-API-Key": API_KEY,
        ...(request.headers.get("cookie")
          ? { Cookie: request.headers.get("cookie")! }
          : {}),
      },
      body: bodyText || "{}",
    }
  );

  const text = await upstream.text();
  const headers = new Headers({ "Content-Type": "application/json" });

  // Forward Set-Cookie back to the browser (toggle likedPostToday{id}).
  // Use getSetCookie() so multiple cookies aren't collapsed/dropped by undici.
  for (const cookie of upstream.headers.getSetCookie()) {
    headers.append("Set-Cookie", cookie);
  }

  return new Response(text, { status: upstream.status, headers });
};
