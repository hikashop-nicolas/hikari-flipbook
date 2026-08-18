// Bundles the shared front-end into the files both packages ship. One bundle,
// two destinations: whatever the two builds disagree about, it is not this.
import { build } from "esbuild";
import { copyFile, mkdir, readFile, writeFile } from "node:fs/promises";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const out = join(root, "dist", "media");

await mkdir(join(out, "js"), { recursive: true });
await mkdir(join(out, "css"), { recursive: true });

// pdf.js wants its worker as a separate file, and the ?url import has to resolve
// to the copy we ship rather than to node_modules.
const worker = "pdf.worker.mjs";
await copyFile(join(root, "node_modules/pdfjs-dist/build", worker), join(out, "js", worker));

const workerUrlPlugin = {
  name: "worker-url",
  setup(b) {
    b.onResolve({ filter: /pdf\.worker\.mjs\?url$/ }, () => ({
      path: "worker-url",
      namespace: "worker-url",
    }));
    b.onLoad({ filter: /.*/, namespace: "worker-url" }, () => ({
      contents: `export default new URL("./${worker}", import.meta.url).href;`,
      loader: "js",
    }));
  },
};

await build({
  entryPoints: [join(root, "src/assets/flipbook.ts")],
  outfile: join(out, "js", "flipbook.js"),
  bundle: true,
  format: "esm",
  target: ["es2022"],
  minify: true,
  sourcemap: false,
  plugins: [workerUrlPlugin],
  logLevel: "warning",
});

const viewer = await readFile(join(root, "node_modules/flipview/dist/flipview.css"), "utf8");
const ours = await readFile(join(root, "src/assets/flipbook.css"), "utf8");
await writeFile(join(out, "css", "flipbook.css"), viewer + "\n" + ours);

console.log("assets built into dist/media");
