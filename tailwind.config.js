/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.{php,html,js}",            // файлы в корне
    "./admin/**/*.{php,html,js}",   // всё внутри admin и подпапок
    "./ajax/**/*.{php,html,js}",    // ajax
    "./api/**/*.{php,html,js}",     // api
    "./cart0/**/*.{php,html,js}",   // cart0
    "./checkoutshopcart/**/*.{php,html,js}", // оформление заказов
    "./client/**/*.{php,html,js}",  // личный кабинет
    "./includes/**/*.{php,html,js}",// includes
    "./myacon/**/*.{php,html,js}",  // myacon
    "./PHPMailer/**/*.{php,html,js}",// PHPMailer (если юзаешь Tailwind в письмах)
    "./text/**/*.{php,html,js}",    // текстовые страницы
    "./uploads/**/*.{php,html,js}", // если вдруг там HTML
    "./assets/js/**/*.{js,php,html}"// твои кастомные JS
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
        'pattern': "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 40 40'%3E%3Cg fill-rule='evenodd'%3E%3Cg fill='%23c4d8d4' fill-opacity='0.78'%3E%3Cpath d='M0 38.59l2.83-2.83 1.41 1.41L1.41 40H0v-1.41zM0 1.4l2.83 2.83 1.41-1.41L1.41 0H0v1.41zM38.59 40l-2.83-2.83 1.41-1.41L40 38.59V40h-1.41zM40 1.41l-2.83 2.83-1.41-1.41L38.59 0H40v1.41zM20 18.6l2.83-2.83 1.41 1.41L21.41 20l2.83 2.83-1.41 1.41L20 21.41l-2.83 2.83-1.41-1.41L18.59 20l-2.83-2.83 1.41-1.41L20 18.59z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E\")",
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
