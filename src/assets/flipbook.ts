// The one front-end entry point both builds ship. It finds every container the
// PHP side emitted and mounts a viewer on it. Nothing here knows which host it
// is running on: the container's data attribute says everything.
import { createFlipview, createPdfSource, createImageSource } from "flipview";
import type { PageSource } from "flipview";
import workerSrc from "pdfjs-dist/build/pdf.worker.mjs?url";

interface Payload {
  kind: "pdf" | "images";
  pages: string[];
  options: Record<string, unknown>;
}

async function mount(el: HTMLElement): Promise<void> {
  const raw = el.dataset.flipbook;
  if (!raw) return;

  let payload: Payload;
  try {
    payload = JSON.parse(raw) as Payload;
  } catch {
    return;
  }

  const source: PageSource =
    payload.kind === "pdf"
      ? await createPdfSource({ url: payload.pages[0], workerSrc })
      : await createImageSource(payload.pages);

  createFlipview(el, source, payload.options);
  el.removeAttribute("data-flipbook");
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
