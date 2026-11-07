import type { ArticleLocale } from "../contracts/article-blocks/v1/types";

export interface Messages {
  nav: { home: string; search: string; contacts: string; login: string; menuToggle: string };
  defaultDescription: string;
  searchPlaceholder: string;
  sidebar: { categories: string; featured: string; recent: string };
  footer: {
    contactInfo: string;
    testimonials: string;
    customCategory: string;
    privacy: string;
    tagline: string;
    testimonial: string;
    role: string;
    noPosts: string;
  };
  article: {
    by: string;
    on: string;
    quoteOfTheDay: string;
    leaveReply: string;
    writeComment: string;
    postComment: string;
    commentFailed: string;
    commentSoon: string;
    shareTelegram: string;
    shareX: string;
    shareLinkedIn: string;
    siteSuffix: string;
  };
}

export const MESSAGES: Record<ArticleLocale, Messages> = {
  en: {
    nav: {
      home: "Homepage",
      search: "Search",
      contacts: "Contacts",
      login: "Login",
      menuToggle: "Toggle navigation menu",
    },
    defaultDescription:
      "Stay updated with the latest trends in Web3 development, software engineering tips, and best coding practices for modern applications.",
    searchPlaceholder: "Search...",
    sidebar: { categories: "Categories", featured: "Featured Posts", recent: "Recent Posts" },
    footer: {
      contactInfo: "Contact Info",
      testimonials: "Testimonials",
      customCategory: "Custom Category Post",
      privacy: "Privacy Policy",
      tagline:
        "Stay updated with the latest trends in Web3 development, software engineering tips, and best coding practices for modern applications.",
      testimonial: "A very good website, it really helped a lot!",
      role: "Client, Tech",
      noPosts: "No published posts yet.",
    },
    article: {
      by: "By",
      on: "On",
      quoteOfTheDay: "Quote of the day:",
      leaveReply: "Leave a reply",
      writeComment: "Write some comment",
      postComment: "Post Comment",
      commentFailed: "Failed to post comment.",
      commentSoon: "your comment will be added soon",
      shareTelegram: "Share on Telegram",
      shareX: "Share on X (Twitter)",
      shareLinkedIn: "Share on LinkedIn",
      siteSuffix: "Web3 Blog",
    },
  },
  ru: {
    nav: {
      home: "Главная",
      search: "Поиск",
      contacts: "Контакты",
      login: "Войти",
      menuToggle: "Открыть или закрыть меню",
    },
    defaultDescription:
      "Следите за новостями Web3-разработки, советами по программной инженерии и лучшими практиками создания современных приложений.",
    searchPlaceholder: "Поиск...",
    sidebar: { categories: "Категории", featured: "Избранные статьи", recent: "Новые статьи" },
    footer: {
      contactInfo: "Контакты",
      testimonials: "Отзывы",
      customCategory: "Избранная категория",
      privacy: "Политика конфиденциальности",
      tagline:
        "Следите за новостями Web3-разработки, советами по программной инженерии и лучшими практиками создания современных приложений.",
      testimonial: "Очень хороший сайт, он действительно сильно помог!",
      role: "Клиент, технологии",
      noPosts: "Опубликованных статей пока нет.",
    },
    article: {
      by: "Автор:",
      on: "Дата:",
      quoteOfTheDay: "Цитата дня:",
      leaveReply: "Оставить комментарий",
      writeComment: "Напишите комментарий",
      postComment: "Отправить",
      commentFailed: "Не удалось отправить комментарий.",
      commentSoon: "ваш комментарий скоро появится",
      shareTelegram: "Поделиться в Telegram",
      shareX: "Поделиться в X (Twitter)",
      shareLinkedIn: "Поделиться в LinkedIn",
      siteSuffix: "Web3 Блог",
    },
  },
};

export function messagesFor(locale: ArticleLocale): Messages {
  return MESSAGES[locale];
}

/** Language names in their own language; adding a language to the switcher is one entry here. */
export const LANGUAGE_LABELS: Record<ArticleLocale, string> = {
  ru: "Русский",
  en: "English",
};
