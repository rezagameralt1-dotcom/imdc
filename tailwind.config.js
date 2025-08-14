export default {
  darkMode: 'class',
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  theme: {
    container: { center: true, padding: '1rem' },
    extend: {
      fontFamily: { sans: ['Inter', 'Vazirmatn', 'ui-sans-serif', 'system-ui'] },
      colors: {
        brand: { 50:'#eef6ff', 100:'#dbeafe', 600:'#2563eb', 700:'#1d4ed8' },
      },
      boxShadow: { soft: '0 8px 30px rgba(0,0,0,0.06)' },
    },
  },
  plugins: [],
}
