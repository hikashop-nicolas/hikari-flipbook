// The one front-end entry point both builds ship. It finds every container the
// PHP side emitted and mounts a viewer on it. Nothing here knows which host it
// is running on: the container's data attribute says everything.
import { createFlipview, createImageSource, createPdfSource, openLightbox, setStrings } from "flipview";
import type { FlipviewOptions, PageSource } from "flipview";
import workerSrc from "pdfjs-dist/build/pdf.worker.mjs?url";

interface Payload {
  kind: "pdf" | "images";
  pages: string[];
  options: FlipviewOptions & { downloadUrl?: string };
  lightbox?: boolean;
  strings?: Record<string, string>;
}

/** A fresh source each time: closing a book destroys the document behind it. */
function open(payload: Payload): Promise<PageSource> {
  return payload.kind === "pdf"
    ? createPdfSource({ url: payload.pages[0], workerSrc })
    : createImageSource(payload.pages);
}

/**
 * In lightbox mode the page shows the cover and nothing else until it is asked
 * for: a book that opens over the page should not cost the page its layout, and
 * a reader who never clicks should not pay for the whole document.
 */
async function mountCover(el: HTMLElement, payload: Payload): Promise<void> {
  const button = document.createElement("button");
  button.type = "button";
  button.className = "hikari-flipbook-cover";
  // The picture is decorative: the button is what has to carry the name.
  button.setAttribute("aria-label", payload.strings?.open || "Open the book");

  const source = await open(payload);
  const canvas = document.createElement("canvas");
  await source.render(0, canvas, 320);
  const img = new Image();
  img.src = canvas.toDataURL("image/webp", 0.9);
  img.alt = "";
  img.decoding = "async";
  button.appendChild(img);
  source.destroy();

  button.addEventListener("click", () => {
    openLightbox(open(payload), payload.options);
  });

  el.appendChild(button);
}

async function mount(el: HTMLElement): Promise<void> {
  const raw = el.dataset.flipbook;
  if (!raw) return;
  el.removeAttribute("data-flipbook");

  let payload: Payload;
  try {
    payload = JSON.parse(raw) as Payload;
  } catch {
    return;
  }

  // The host's words, before anything is built with them.
  if (payload.strings) setStrings(payload.strings);

  if (payload.lightbox) {
    await mountCover(el, payload);
    return;
  }

  createFlipview(el, await open(payload), payload.options);
}

function start(): void {
  document.querySelectorAll<HTMLElement>(".hikari-flipbook[data-flipbook]").forEach((el) => {
    void mount(el);
  });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", start);
} else {
  start();
}
