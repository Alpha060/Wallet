# Full UI Redesign: Apple Cards & Glassmorphism

This plan outlines the steps to overhaul the UI across all pages (Login, Register, User Dashboard, Admin Dashboard, etc.) to achieve a highly polished, professional "Apple Cards UI" aesthetic with fluid animations and extreme smoothness.

## User Review Required

> [!IMPORTANT]
> Please review this design overhaul plan. This will touch the global CSS file (`app.css`) and the HTML structure of all major pages. Your approval is required to proceed with these sweeping visual changes.

## Proposed Changes

### 1. Global CSS Overhaul (`app.css`)
The foundational design system will be upgraded to mimic Apple's Human Interface Guidelines:
- **Root Variables:**
  - Increase border radii (`--radius: 32px`, `--radius-sm: 20px` or `24px`) to create the signature soft card look.
  - Enhance `--glass-blur` to `blur(40px)` for extreme translucency.
  - Adjust shadows to be larger, softer, and multi-layered (e.g., `box-shadow: 0 20px 40px rgba(0,0,0,0.08), 0 1px 3px rgba(0,0,0,0.05)`).
- **Typography:**
  - Enforce system-UI typography stacking (`font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif`).
  - Improve letter spacing and font-weight contrasts (e.g., extremely bold for balances, highly muted for secondary text).
- **Animations:**
  - Upgrade hover transitions to use Apple-like bouncy bezier curves (`transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1)`).
  - Add smooth scale-up effects to all cards and buttons on hover (`transform: scale(1.02)`).

### 2. Component Refinements
- **`.glass-card` & `.glass-panel`:** 
  - Update background opacity to be more translucent.
  - Add an inset subtle inner border (`box-shadow: inset 0 1px 1px rgba(255,255,255,0.2)`) to give the glass edge a realistic highlight.
- **Buttons (`.btn-primary`, `.btn-secondary`):**
  - Make buttons completely pill-shaped (`border-radius: 9999px`) or highly rounded, depending on the context.
  - Add a soft glow effect that expands slightly on hover.
- **Form Inputs:**
  - Make inputs taller and more pill-like.
  - Improve focus states with a soft outline ring instead of a harsh border.

### 3. Page-Specific Upgrades

#### [MODIFY] [app.css](file:///c:/Users/rajhr/OneDrive/Documents/Desktop/Wallet%20Management%20System/Wallet/app.css)
- Implement all the core token changes (colors, borders, shadows, animations, and typography).

#### [MODIFY] [login.php](file:///c:/Users/rajhr/OneDrive/Documents/Desktop/Wallet%20Management%20System/Wallet/login.php)
- Upgrade the login card to look like a floating Apple interface window.
- Improve the background orbs to be larger and softer.
- Ensure the input fields follow the new soft, pill-shaped design.

#### [MODIFY] [register.php](file:///c:/Users/rajhr/OneDrive/Documents/Desktop/Wallet%20Management%20System/Wallet/register.php) & [forgot-password.php](file:///c:/Users/rajhr/OneDrive/Documents/Desktop/Wallet%20Management%20System/Wallet/forgot-password.php)
- Replicate the exact design upgrades from the login page for consistency.

#### [MODIFY] [dashboard.php](file:///c:/Users/rajhr/OneDrive/Documents/Desktop/Wallet%20Management%20System/Wallet/dashboard.php) (User Dashboard)
- **Wallet Balance Card:** Redesign to physically resemble a digital credit card (like Apple Card) with a subtle gradient and embedded logo.
- **Bento Grid:** Tighten the layout to ensure all cards align perfectly into a cohesive dashboard grid.
- **Modals:** Add a smooth slide-up animation for all modals (like the deposit wizard and purchase confirmation).

#### [MODIFY] [admin.php](file:///c:/Users/rajhr/OneDrive/Documents/Desktop/Wallet%20Management%20System/Wallet/admin.php) (Admin Dashboard)
- Ensure the admin statistics cards match the new Bento Grid style.
- Upgrade the split-pane layouts (for deposit/withdrawal approvals) so they feel like native macOS/iPadOS split views with a translucent sidebar and solid content area.

## Verification Plan

### Manual Verification
1. I will deploy the changes and ask you to refresh the server (`http://localhost:8000`).
2. You will visually verify the aesthetic on the Login page.
3. You will log in as the user (`user@example.com`) to experience the new User Dashboard card layouts and hover animations.
4. You will log in as the admin (`admin@example.com`) to verify the admin layout retains its functionality while looking significantly more premium.
