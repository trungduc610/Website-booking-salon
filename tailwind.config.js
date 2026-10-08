module.exports = {
  content: ["./resources/views/**/*.blade.php", "./public/js/**/*.js"],
  darkMode: ["selector", '[data-theme="dark"]'],
  theme: {
    extend: {
      colors: {
        ivory: "#FAFAF9",
        cream: "#F5F2EC",
        rose: "#C88A75",
        terracotta: "#B3715D",
        sage: "#5A7361",
        obsidian: "#1A1A1A",
        line: "#E5E2DC",
      },
      fontFamily: {
        display: ['"Noto Serif Variable"', "Georgia", "serif"],
        sans: ['"Be Vietnam Pro"', '"Segoe UI"', "sans-serif"],
      },
      boxShadow: { soft: "0 8px 30px rgb(0 0 0 / 0.04)" },
      borderRadius: { organic: "2rem" },
    },
  },
  corePlugins: { preflight: false },
};
