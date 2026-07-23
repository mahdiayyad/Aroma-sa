# UI Component Design System

Create a professional, modern, and consistent UI component system for the entire application.

The design should feel like a premium Saudi e-commerce platform.

## General Requirements

- All UI components must have a clean, modern, professional appearance.
- Maintain consistency across all pages.
- Use reusable CSS classes instead of inline styles.
- Follow Bootstrap 5.3 best practices.
- The design should be responsive for desktop, tablet, and mobile.
- Support both Arabic RTL and English LTR layouts.

---

# Buttons

Create a primary button style that will be used across the application.

Requirements:

- Premium modern appearance.
- Smooth hover and active states.
- Rounded corners.
- Proper padding and typography.
- Professional color palette matching the brand identity.
- Include:
  - Primary button
  - Secondary button
  - Success button
  - Danger button
  - Outline buttons
  - Loading state
  - Disabled state

Example usage:

Primary actions:
- Add to cart
- Checkout
- Save
- Submit forms

The buttons should look like a premium e-commerce brand, not default Bootstrap buttons.

---

# Form Inputs

Create a reusable input design system.

Requirements:

- Modern input fields.
- Clear labels.
- Proper spacing.
- Validation states.
- Error messages.
- Success states.
- Disabled states.
- Required indicators.

Inputs should include:

- Text inputs
- Password inputs
- Number inputs
- Date inputs
- Textareas

Avoid default browser styling.

---

# Select Components

All select elements must use Select2.

Requirements:

- Integrate Select2 globally.
- Create a custom Select2 theme matching the application design.
- Support:
  - Search
  - Clear selection
  - Placeholder
  - Multiple selection
  - AJAX loading when needed

The Select2 dropdown must match the input fields visually.

Example:

- Same height
- Same border radius
- Same colors
- Same typography

---

# Form Layout

Create professional form layouts:

- Two-column forms on desktop.
- Single-column forms on mobile.
- Proper grouping of related fields.
- Card-based sections when needed.

Example:

Product creation form:
- Basic information
- Images
- Pricing
- Inventory
- SEO

Each section should have a clear visual separation.

---

# CSS Architecture

Create:

resources/css/components/

Structure:

components/
    buttons.css
    inputs.css
    select2.css
    cards.css
    forms.css

Keep styles modular and maintainable.

---

# Quality Standard

The final UI should look similar in quality to premium SaaS dashboards and modern Saudi e-commerce websites.

Do not use default Bootstrap appearance.
Customize everything to create a unique professional brand experience.