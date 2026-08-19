// The one front-end entry point both builds ship. It finds every container the
// PHP side emitted and mounts a viewer on it. Nothing here knows which host it
// is running on: the container's data attribute says everything.
import {
  createEpubSource,
  createFlipview,
  createImageSource,
  createPdfSource,
  openLightbox,
  setStrings,
} from "flipview";
import type { FlipviewOptions, PageSource } from "flipview";
import workerSrc from "pdfjs-dist/build/pdf.worker.mjs?url";

/**
 * pdf.js's own decoders, shipped beside this bundle. A scanned catalogue is JPEG
 * 2000 or JBIG2, and without these it renders as blank white pages with nothing
 * logged, which is the hardest kind of broken to report.
 */
const wasmUrl = new URL("./wasm/", import.meta.url).href;

interface Payload {
  kind: "pdf" | "images" | "epub";
  pages: string[];
  options: FlipviewOptions & { downloadUrl?: string };
  lightbox?: boolean;
  /** A picture of the first page the host made for us, if it could. */
  cover?: string;
  showHotspots?: boolean;
  analytics?: "" | "dataLayer" | "gtag";
  strings?: Record<string, string>;
}

/**
 * Reports what a reader does.
 *
 * Every book fires a `hikari-flipbook` event on its own container, whatever the
 * site's settings say: a site can listen for that without asking anyone, and it
 * costs a page nothing. The setting only decides whether the same thing is also
 * handed to an analytics service the page already has.
 */
function reporter(el: HTMLElement, payload: Payload) {
  return (name: string, detail: Record<string, unknown>): void => {
    const data = { book: el.id, document: payload.pages[0], ...detail };

    el.dispatchEvent(new CustomEvent("hikari-flipbook", { detail: { name, ...data }, bubbles: true }));

    const site = window as unknown as {
      dataLayer?: unknown[];
      gtag?: (kind: string, action: string, params: Record<string, unknown>) => void;
    };

    // A service the page does not have is not an error: nothing is loaded for
    // this, and nothing is waited for.
    if (payload.analytics === "dataLayer" && Array.isArray(site.dataLayer)) {
      site.dataLayer.push({ event: `flipbook_${name}`, ...data });
    } else if (payload.analytics === "gtag" && typeof site.gtag === "function") {
      site.gtag("event", `flipbook_${name}`, data);
    }
  };
}

/** A fresh source each time: closing a book destroys the document behind it. */
function open(payload: Payload): Promise<PageSource> {
  if (payload.kind === "epub") return createEpubSource({ url: payload.pages[0] });

  return payload.kind === "pdf"
    ? createPdfSource({ url: payload.pages[0], workerSrc, wasmUrl })
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

  const img = new Image();
  img.alt = "";
  img.decoding = "async";

  if (payload.cover) {
    // The host already drew it. Nothing of the document is fetched until a
    // reader actually opens the book.
    img.src = payload.cover;
    img.loading = "lazy";
  } else {
    const source = await open(payload);
    const canvas = document.createElement("canvas");
    await source.render(0, canvas, 320);
    img.src = canvas.toDataURL("image/webp", 0.9);
    source.destroy();
  }

  button.appendChild(img);

  button.addEventListener("click", () => {
    openLightbox(open(payload), { ...payload.options, onEvent: reporter(el, payload) });
    if (payload.showHotspots) {
      // The lightbox builds its own root, on the body rather than in here.
      requestAnimationFrame(() => document.querySelector(".fv-lightbox .fv-root")?.classList.add("fv-hotspots-shown"));
    }
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

  createFlipview(el, await open(payload), { ...payload.options, onEvent: reporter(el, payload) });

  if (payload.showHotspots) el.querySelector(".fv-root")?.classList.add("fv-hotspots-shown");
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
