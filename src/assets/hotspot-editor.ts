// The screen where a site draws the regions on a book's pages. It is shipped by
// both builds and mounted by both admin sides: the only thing either host has to
// do is emit the container and a hidden input, and the JSON in that input is what
// gets stored.
import { createImageSource, createPdfSource } from "flipview";
import type { PageSource } from "flipview";
import workerSrc from "pdfjs-dist/build/pdf.worker.mjs?url";

/** As in the viewer: without these a scan is blank pages to draw hotspots on. */
const wasmUrl = new URL("./wasm/", import.meta.url).href;

interface Spot {
  page: number;
  x: number;
  y: number;
  width: number;
  height: number;
  href?: string;
  target?: string;
  goToPage?: number;
  label?: string;
  data?: Record<string, string>;
}

interface Payload {
  kind: "pdf" | "images";
  pages: string[];
  strings?: Record<string, string>;
}

let words: Record<string, string> = {};
const say = (key: string, fallback: string): string => words[key] || fallback;

/** The smallest region worth keeping, as a fraction of the page. */
const MIN = 0.01;

const pc = (value: number): string => `${Math.round(value * 1000) / 10}`;
const clamp = (value: number): number => Math.min(1, Math.max(0, value));

function el<K extends keyof HTMLElementTagNameMap>(
  tag: K,
  className?: string,
  text?: string,
): HTMLElementTagNameMap[K] {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

function field(label: string, input: HTMLElement): HTMLElement {
  const wrap = el("label", "hikari-hs-field");
  wrap.appendChild(el("span", undefined, label));
  wrap.appendChild(input);
  return wrap;
}

async function mount(root: HTMLElement): Promise<void> {
  const raw = root.dataset.hotspotEditor;
  const store = root.querySelector<HTMLInputElement>("input[type=hidden]");
  if (!raw || !store) return;
  root.removeAttribute("data-hotspot-editor");

  let payload: Payload;
  try {
    payload = JSON.parse(raw) as Payload;
  } catch {
    return;
  }
  words = payload.strings ?? {};

  let spots: Spot[] = [];
  try {
    const parsed = JSON.parse(store.value || "[]");
    if (Array.isArray(parsed)) spots = parsed as Spot[];
  } catch {
    spots = [];
  }

  let source: PageSource;
  try {
    source =
      payload.kind === "pdf"
        ? await createPdfSource({ url: payload.pages[0], workerSrc, wasmUrl })
        : await createImageSource(payload.pages);
  } catch {
    root.appendChild(el("p", "hikari-hs-error", say("unreadable", "This book could not be opened.")));
    return;
  }

  let page = 0;
  let selected = -1;

  // --- the frame -------------------------------------------------------------
  const bar = el("div", "hikari-hs-bar");
  const prev = el("button", "hikari-hs-btn", "‹");
  prev.type = "button";
  prev.title = say("prev", "Previous page");
  const next = el("button", "hikari-hs-btn", "›");
  next.type = "button";
  next.title = say("next", "Next page");
  const count = el("span", "hikari-hs-count");
  const add = el("button", "hikari-hs-btn hikari-hs-add", say("add", "Add a region"));
  add.type = "button";
  bar.append(prev, count, next, add);

  const stage = el("div", "hikari-hs-stage");
  const canvas = el("canvas", "hikari-hs-canvas");
  const layer = el("div", "hikari-hs-layer");
  stage.append(canvas, layer);

  const detail = el("div", "hikari-hs-detail");
  const help = el("p", "hikari-hs-help", say("help", "Drag on the page to draw a region."));

  root.append(bar, stage, help, detail);

  // --- the page --------------------------------------------------------------
  async function paint(): Promise<void> {
    count.textContent = `${page + 1} / ${source.pageCount}`;
    prev.disabled = page === 0;
    next.disabled = page >= source.pageCount - 1;
    try {
      await source.render(page, canvas, 900);
    } catch {
      /* a page that will not paint still takes regions */
    }
    draw();
  }

  // --- the regions -----------------------------------------------------------
  function draw(): void {
    layer.replaceChildren();

    spots.forEach((spot, index) => {
      if (spot.page !== page) return;

      const box = el("div", "hikari-hs-spot");
      box.style.left = `${pc(spot.x)}%`;
      box.style.top = `${pc(spot.y)}%`;
      box.style.width = `${pc(spot.width)}%`;
      box.style.height = `${pc(spot.height)}%`;
      box.classList.toggle("is-selected", index === selected);
      box.tabIndex = 0;
      box.setAttribute("role", "button");
      box.setAttribute("aria-label", spot.label || say("region", "Region"));
      if (spot.label) box.appendChild(el("span", "hikari-hs-tag", spot.label));

      const grip = el("span", "hikari-hs-grip");
      box.appendChild(grip);

      const pick = () => {
        selected = index;
        draw();
        showDetail();
      };
      box.addEventListener("pointerdown", (e) => {
        pick();
        move(e, index, e.target === grip ? "size" : "shift");
      });
      box.addEventListener("focus", pick);

      layer.appendChild(box);
    });

    save();
  }

  function save(): void {
    store.value = JSON.stringify(spots);
    // Both hosts watch their own inputs for a change before they warn about
    // leaving a page with unsaved work.
    store.dispatchEvent(new Event("change", { bubbles: true }));
  }

  // --- drawing and dragging --------------------------------------------------
  function fractions(event: PointerEvent): { x: number; y: number } {
    const rect = layer.getBoundingClientRect();
    return {
      x: clamp((event.clientX - rect.left) / rect.width),
      y: clamp((event.clientY - rect.top) / rect.height),
    };
  }

  layer.addEventListener("pointerdown", (event) => {
    if (event.target !== layer) return;
    const start = fractions(event);
    const spot: Spot = { page, x: start.x, y: start.y, width: 0, height: 0 };
    spots.push(spot);
    selected = spots.length - 1;
    const index = selected;

    const onMove = (e: PointerEvent) => {
      const at = fractions(e);
      spot.x = Math.min(start.x, at.x);
      spot.y = Math.min(start.y, at.y);
      spot.width = Math.abs(at.x - start.x);
      spot.height = Math.abs(at.y - start.y);
      draw();
    };

    const onUp = () => {
      layer.removeEventListener("pointermove", onMove);
      layer.removeEventListener("pointerup", onUp);
      // A click rather than a drag: no region was meant.
      if (spot.width < MIN || spot.height < MIN) {
        spots.splice(index, 1);
        selected = -1;
      }
      draw();
      showDetail();
    };

    layer.setPointerCapture(event.pointerId);
    layer.addEventListener("pointermove", onMove);
    layer.addEventListener("pointerup", onUp);
  });

  function move(event: PointerEvent, index: number, what: "shift" | "size"): void {
    event.preventDefault();
    const spot = spots[index];
    const from = fractions(event);
    const was = { ...spot };

    const onMove = (e: PointerEvent) => {
      const at = fractions(e);
      if (what === "shift") {
        spot.x = clamp(was.x + (at.x - from.x));
        spot.y = clamp(was.y + (at.y - from.y));
      } else {
        spot.width = clamp(Math.max(MIN, was.width + (at.x - from.x)));
        spot.height = clamp(Math.max(MIN, was.height + (at.y - from.y)));
      }
      draw();
    };

    const onUp = () => {
      layer.removeEventListener("pointermove", onMove);
      layer.removeEventListener("pointerup", onUp);
      showDetail();
    };

    layer.setPointerCapture(event.pointerId);
    layer.addEventListener("pointermove", onMove);
    layer.addEventListener("pointerup", onUp);
  }

  // --- one region's settings -------------------------------------------------
  function showDetail(): void {
    detail.replaceChildren();
    const spot = spots[selected];

    if (!spot) {
      detail.appendChild(el("p", "hikari-hs-none", say("none", "No region selected.")));
      return;
    }

    const label = el("input", "hikari-hs-input");
    label.type = "text";
    label.value = spot.label ?? "";
    label.addEventListener("input", () => {
      spot.label = label.value;
      draw();
    });

    const href = el("input", "hikari-hs-input");
    href.type = "text";
    href.value = spot.href ?? "";
    href.placeholder = "https://";
    href.addEventListener("input", () => {
      spot.href = href.value || undefined;
      save();
    });

    const tab = el("input");
    tab.type = "checkbox";
    tab.checked = spot.target === "_blank";
    tab.addEventListener("change", () => {
      spot.target = tab.checked ? "_blank" : undefined;
      save();
    });

    const jump = el("input", "hikari-hs-input");
    jump.type = "number";
    jump.min = "1";
    jump.max = String(source.pageCount);
    jump.value = spot.goToPage === undefined ? "" : String(spot.goToPage + 1);
    jump.addEventListener("input", () => {
      const value = Number(jump.value);
      spot.goToPage = jump.value === "" || !Number.isFinite(value) ? undefined : Math.max(0, value - 1);
      save();
    });

    const product = el("input", "hikari-hs-input");
    product.type = "text";
    product.value = spot.data?.product ?? "";
    product.addEventListener("input", () => {
      spot.data = product.value ? { ...spot.data, product: product.value } : undefined;
      save();
    });

    // Position and size as numbers as well as by dragging, so the whole editor
    // can be used from a keyboard.
    const boxes = (["x", "y", "width", "height"] as const).map((key) => {
      const input = el("input", "hikari-hs-input hikari-hs-num");
      input.type = "number";
      input.min = "0";
      input.max = "100";
      input.step = "0.1";
      input.value = pc(spot[key]);
      input.addEventListener("input", () => {
        spot[key] = clamp(Number(input.value) / 100);
        draw();
      });
      return field(say(key, key), input);
    });

    const remove = el("button", "hikari-hs-btn hikari-hs-remove", say("remove", "Delete this region"));
    remove.type = "button";
    remove.addEventListener("click", () => {
      spots.splice(selected, 1);
      selected = -1;
      draw();
      showDetail();
    });

    detail.append(
      field(say("label", "Name"), label),
      field(say("href", "Link"), href),
      field(say("tab", "Open in a new tab"), tab),
      field(say("jump", "Go to page"), jump),
      field(say("product", "Product id"), product),
      ...boxes,
      remove,
    );
  }

  // --- wiring ----------------------------------------------------------------
  prev.addEventListener("click", () => {
    if (page > 0) {
      page--;
      selected = -1;
      void paint();
      showDetail();
    }
  });

  next.addEventListener("click", () => {
    if (page < source.pageCount - 1) {
      page++;
      selected = -1;
      void paint();
      showDetail();
    }
  });

  add.addEventListener("click", () => {
    spots.push({ page, x: 0.3, y: 0.4, width: 0.4, height: 0.2 });
    selected = spots.length - 1;
    draw();
    showDetail();
  });

  showDetail();
  await paint();
}

function start(): void {
  document
    .querySelectorAll<HTMLElement>(".hikari-hotspots[data-hotspot-editor]")
    .forEach((node) => void mount(node));
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", start);
} else {
  start();
}
