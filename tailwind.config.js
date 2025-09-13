/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./**/*.php",
    "./assets/**/*.js",
    "./includes/**/*.php",
    "./admin/**/*.php",
    "./client/**/*.php",
    "./checkoutshopcart/**/*.php",
    "./cart0/**/*.php"
  ],
  theme: {
    extend: {
      colors: {
        // Основные цвета вашего проекта
        emerald: '#118568',
        litegreen: '#17B890',
        dirtgreen: '#5E807F',
        litedirtgreen: '#9DC5BB',
        litegray: '#DEE5E5',
        
        // Альтернативные названия для удобства использования
        primary: '#118568',
        secondary: '#17B890',
        accent: '#5E807F',
        light: '#9DC5BB',
        background: '#DEE5E5',
        
        // Градиенты
        'gradient-start': '#118568',
        'gradient-end': '#17B890',
      },
      
      // Градиенты
      backgroundImage: {
        'gradient-primary': 'linear-gradient(to right, #118568, #17B890)',
        'gradient-secondary': 'linear-gradient(to right, #17B890, #5E807F)',
        'gradient-accent': 'linear-gradient(to right, #5E807F, #9DC5BB)',
        'gradient-background': 'linear-gradient(to bottom right, #DEE5E5, #9DC5BB)',
        'pattern': "url(\"data:image/svg+xml,%3Csvg width='20' height='20' viewBox='0 0 20 20' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%239DC5BB' fill-opacity='0.4' fill-rule='evenodd'%3E%3Ccircle cx='3' cy='3' r='3'/%3E%3Ccircle cx='13' cy='13' r='3'/%3E%3C/g%3E%3C/svg%3E\")",
      },
      
      // Тени
      boxShadow: {
        'xl': '0 20px 25px -5px rgba(17, 133, 104, 0.1), 0 10px 10px -5px rgba(17, 133, 104, 0.04)',
        '2xl': '0 25px 50px -12px rgba(17, 133, 104, 0.25)',
      },
      
      // Анимации
      animation: {
        'spin-slow': 'spin 3s linear infinite',
        'ping-slow': 'ping 3s cubic-bezier(0,0,0.2,1) infinite',
        'bounce-slow': 'bounce 3s infinite',
      },
      
      // Скругления
      borderRadius: {
        'xl': '1rem',
        '2xl': '1.5rem',
        '3xl': '2rem',
      },
    },
  },
  plugins: [],
  // Отключение предупреждений о deprecated purge/content опциях
  // (если вы используете Tailwind CSS v3.0+)
  // future: {
  //   removeDeprecatedGapUtilities: true,
  //   purgeLayersByDefault: true,
  // },
}
