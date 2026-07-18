import { defineConfig } from 'vitepress'

const base = process.env.VITEPRESS_BASE || '/'

export default defineConfig({
  base,
  title: 'modern-bx/cli',
  description: 'Документация консольного помощника для Bitrix',
  lang: 'ru-RU',
  outDir: '../dist',
  vite: {
    server: {
      watch: {
        ignored: ['**/node_modules/**', '**/dist/**', '**/.vitepress/cache/**']
      }
    }
  },
  cleanUrls: true,
  themeConfig: {
    nav: [
      { text: 'Обзор', link: '/' },
      { text: 'Команды', link: '/commands/' },
      { text: 'Архитектура', link: '/guide/architecture' }
    ],
    sidebar: [
      {
        text: 'Руководство',
        items: [
          { text: 'Введение', link: '/' },
          { text: 'Установка и сборка', link: '/guide/install' },
          { text: 'Архитектура и логика', link: '/guide/architecture' },
          { text: 'Удалённые проекты', link: '/guide/remotes' }
        ]
      },
      {
        text: 'Команды',
        items: [
          { text: 'Все команды', link: '/commands/' },
          { text: 'Bitrix', link: '/commands/bitrix' },
          { text: 'Резервные копии', link: '/commands/backup' },
          { text: 'База данных', link: '/commands/database' },
          { text: 'Файлы', link: '/commands/files' },
          { text: 'JSON и dotenv', link: '/commands/data' },
          { text: 'Remote и сессии', link: '/commands/remote' },
          { text: 'Системные команды', link: '/commands/system' }
        ]
      }
    ],
    search: { provider: 'local' },
    outline: { level: [2, 3], label: 'На странице' },
    docFooter: { prev: 'Назад', next: 'Далее' },
    darkModeSwitchLabel: 'Тема',
    sidebarMenuLabel: 'Меню',
    returnToTopLabel: 'Наверх'
  }
})
