const fs = require("node:fs");
const path = require("node:path");
fs.mkdirSync("public/vendor/alpine", { recursive: true });
fs.copyFileSync(
  "node_modules/@alpinejs/csp/dist/cdn.min.js",
  "public/vendor/alpine/alpine.min.js",
);
fs.mkdirSync("public/vendor/fonts", { recursive: true });
const fonts = [
  ["@fontsource-variable/noto-serif", ["wght.css", "wght-italic.css"]],
  ["@fontsource/be-vietnam-pro", ["400.css", "600.css"]],
];
let css = "";
for (const [pkg, sheets] of fonts) {
  const base = path.join("node_modules", pkg);
  for (const sheet of sheets) {
    const source = fs.readFileSync(path.join(base, sheet), "utf8");
    for (let face of source.match(/@font-face\s*\{[^}]+\}/g)) {
      if (!/-(vietnamese|latin|latin-ext)-/.test(face)) continue;
      face = face.replace(/url\(\.\/files\/([^)]*)\)/g, (_, file) => {
        fs.copyFileSync(
          path.join(base, "files", file),
          path.join("public/vendor/fonts", file),
        );
        return `url('/vendor/fonts/${file}')`;
      });
      css += face + "\n";
    }
  }
  const license = path.join(base, "LICENSE");
  if (fs.existsSync(license))
    fs.copyFileSync(
      license,
      path.join("public/vendor/fonts", pkg.split("/").pop() + "-LICENSE.txt"),
    );
}
fs.writeFileSync("public/css/fonts.css", css);

