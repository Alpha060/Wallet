// Theme Management
export const ThemeManager = {
  init() {
    // Load saved theme or default to light
    const savedTheme = localStorage.getItem('theme') || 'light';
    this.setTheme(savedTheme);
  },

  setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
  },

  toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    this.setTheme(newTheme);
    return newTheme;
  },

  getCurrentTheme() {
    return document.documentElement.getAttribute('data-theme') || 'light';
  }
};

// Initialize theme on load
if (typeof window !== 'undefined') {
  ThemeManager.init();
}
