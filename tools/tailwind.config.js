/** Mirrors the w5d template's inline tailwind.config (brand / ink palette, Inter + Space Grotesk). */
module.exports = {
  darkMode: 'class',
  content: ['../views/**/*.php', '../src/**/*.php', '../htdocs/assets/_base/js/site.js', '../data/content/**/*.json'],
  safelist: [
    { pattern: /^(sm:|md:|lg:)?grid-cols-(1|2|3|4|5|6)$/ },{ pattern: /^(bg|text|border|from|to|via)-(emerald|rose|amber|sky|violet|cyan|indigo)-(300|400|500|600|700)(\/\d+)?$/ }],
  theme: {
    extend: {
      colors: {
        brand: { 50: '#f3f1ff', 100: '#ebe5ff', 200: '#d9ceff', 300: '#bda7ff', 400: '#9c79ff', 500: '#8b5cf6', 600: '#7c3aed', 700: '#6d28d9', 800: '#5b21b6', 900: '#4c1d95' },
        ink: { 950: '#070711', 900: '#0b0b18', 800: '#11111f', 700: '#1a1a2e' },
      },
      fontFamily: {
        sans: ['Inter', 'Noto Sans Arabic', 'system-ui', 'sans-serif'],
        display: ['"Space Grotesk"', 'Inter', 'Noto Sans Arabic', 'sans-serif'],
        arabic: ['"Noto Sans Arabic"', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [],
};
