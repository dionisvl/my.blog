import type { APIRoute } from "astro";
import { ApiClientError, postComment } from "../../lib/api";

export const prerender = false;

export const POST: APIRoute = async ({ request }) => {
  // Accept either form-encoded (from a normal <form> submit) or JSON (from fetch).
  let message = "";
  let post_id = 0;
  let countMe = 0;
  let honeypot = "";

  const contentType = request.headers.get("content-type") ?? "";

  if (contentType.includes("application/json")) {
    let b: Record<string, unknown>;
    try {
      b = await request.json();
    } catch {
      return new Response(
        JSON.stringify({ status: "error", message: "Invalid JSON body" }),
        { status: 400, headers: { "Content-Type": "application/json" } }
      );
    }
    message = String(b.message ?? "");
    post_id = Number(b.post_id ?? 0);
    countMe = Number(b.countMe ?? 0);
    honeypot = String(b.honeypot ?? "");
  } else {
    const form = await request.formData();
    message = String(form.get("message") ?? "");
    post_id = Number(form.get("post_id") ?? 0);
    countMe = Number(form.get("countMe") ?? 0);
    honeypot = String(form.get("honeypot") ?? "");
  }

  try {
    const result = await postComment({ message, post_id, countMe, honeypot });
    return new Response(JSON.stringify(result), {
      status: 200,
      headers: { "Content-Type": "application/json" },
    });
  } catch (error) {
    if (error instanceof ApiClientError) {
      return new Response(
        JSON.stringify({
          status: "error",
          message: error.body || "Comment failed",
        }),
        {
          status: error.status,
          headers: { "Content-Type": "application/json" },
        }
      );
    }
    return new Response(
      JSON.stringify({ status: "error", message: "Comment failed" }),
      {
        status: 500,
        headers: { "Content-Type": "application/json" },
      }
    );
  }
};
