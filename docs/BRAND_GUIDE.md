
# 🎨 RePlug — Brand & UI Style Guide
**Tagline:** Recycle • Repair • Reuse

---

## 1. Brand Personality

**RePlug is:**
- 🌱 Sustainable and environmentally responsible  
- ⚙️ Practical and technology-focused (electronics-first)  
- 🤝 Trustworthy and community-oriented  
- 🧠 Simple, clean, and functional  

**RePlug is NOT:**
- ❌ Playful or childish  
- ❌ Overly corporate or enterprise-heavy  
- ❌ Flashy, neon, or gradient-heavy UI  
- ❌ A general marketplace-first platform  

Tone to maintain:
> “A modern recycling depot powered by clean, reliable technology.”

---

## 2. Logo Usage

### Primary Icon
- Use the **icon-only (plug + recycling arrows)** version for:
  - Navigation bar
  - Login / Register pages
  - Dashboards
  - Favicon and app icon
  - Mobile layouts

### Clear Space
- Maintain padding equal to **½ the icon width** on all sides.

### Approved Backgrounds
- White: `#FFFFFF`
- Light Gray: `#F7F9FB`
- Dark Slate: `#1F2933`

### Do Not
- ❌ Add shadows or glow effects  
- ❌ Rotate, stretch, or distort the icon  
- ❌ Change colors outside the palette  
- ❌ Add text inside the icon  

---

## 3. Color Palette (Strict)

### Primary Colors
| Purpose | HEX |
|------|-----|
| Eco Green | `#2FAE66` |
| Tech Blue | `#1E88E5` |

### Neutral Colors
| Purpose | HEX |
|------|-----|
| Primary Text | `#1F2933` |
| Secondary Text | `#5F6C7B` |
| Borders | `#E5E7EB` |
| Background | `#F7F9FB` |
| White | `#FFFFFF` |

### Color Rules
- Green = sustainability, success, positive actions  
- Blue = primary actions, navigation, links  
- Avoid gradients in UI (logo exception only)

---

## 4. Typography

### Primary Font
**Inter**  
Fallback:
`system-ui, -apple-system, Segoe UI, Roboto, sans-serif`

### Font Weights
| Usage | Weight |
|----|----|
| Headings | 600–700 |
| Body | 400 |
| Labels | 500 |

### Font Sizes
- H1: 28–32px  
- H2: 22–24px  
- H3: 18–20px  
- Body: 14–16px  
- Small text: 12–13px  

Rule: Readability over decoration.

---

## 5. UI Components

### Buttons

**Primary Button**
- Background: `#1E88E5`
- Text: White
- Radius: 6px
- Hover: `#1565C0`

**Secondary Button**
- Background: White
- Border: `1px solid #E5E7EB`
- Text: Dark gray

**Danger Button**
- Background: `#E53935`
- Use only for destructive actions

---

### Forms
- Input height: 40–44px
- Border: `1px solid #E5E7EB`
- Focus state: Blue border
- Labels always visible above inputs

Validation:
- Success: Green
- Error: Red with clear message

---

### Cards
Used for:
- Items
- Pickups
- Marketplace listings

Style:
- White background
- Border: `1px solid #E5E7EB`
- Border radius: 8px
- Minimal or no shadow

---

## 6. Icons & Imagery

### Icons
- Outline-style icons (Heroicons / Feather)
- Consistent stroke width
- No cartoon or emoji icons

### Images
- Real electronics and appliances
- Neutral backgrounds
- No stock “happy people” photos
- Focus on items, not faces

---

## 7. Status Color System

| Status | Color |
|-----|------|
| Draft | Gray |
| Pickup Requested | Blue |
| Scheduled | Blue |
| Picked Up | Teal |
| Inspected | Purple |
| Repairable | Orange |
| Approved for Sale | Green |
| Sold | Dark Green |
| Recycled / Disposed | Gray |

---

## 8. Dashboard Layout

### Structure
- Left sidebar on desktop
- Top navigation on mobile
- Role-based dashboards:
  - Recycler
  - Driver
  - Technician
  - Admin

### Design Principles
- Information-first
- Clean tables
- Clear status badges
- Minimal charts

---

## 9. Accessibility & UX

- WCAG AA color contrast
- Buttons must have text labels
- Icon-only actions require tooltips
- Clear, human-readable error messages

---

## 10. Cursor Usage Instructions

When generating UI or frontend code in Cursor, include:

> “Follow the RePlug Brand Guide strictly: eco-tech aesthetic, green/blue palette, Inter font, clean card-based layout, minimal shadows, recycling-first tone.”

---

## 11. Brand Summary

> **RePlug is a recycling-first web application that enables responsible pickup, inspection, repair, and reuse of electronics through a clean, trustworthy, eco-tech interface.**

---
End of Document
