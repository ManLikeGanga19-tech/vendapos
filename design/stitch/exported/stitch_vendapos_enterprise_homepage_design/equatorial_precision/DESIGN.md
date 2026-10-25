---
name: Equatorial Precision
colors:
  surface: '#151024'
  surface-dim: '#151024'
  surface-bright: '#3c364c'
  surface-container-lowest: '#100b1f'
  surface-container-low: '#1d182d'
  surface-container: '#221c31'
  surface-container-high: '#2c273c'
  surface-container-highest: '#373147'
  on-surface: '#e8defb'
  on-surface-variant: '#e2bfb4'
  inverse-surface: '#e8defb'
  inverse-on-surface: '#332d43'
  outline: '#a98a80'
  outline-variant: '#594139'
  surface-tint: '#ffb59c'
  primary: '#ffb59c'
  on-primary: '#5c1900'
  primary-container: '#f4622a'
  on-primary-container: '#511500'
  inverse-primary: '#ab3600'
  secondary: '#5cdf78'
  on-secondary: '#003913'
  secondary-container: '#0ea747'
  on-secondary-container: '#00320f'
  tertiary: '#c9c3dd'
  on-tertiary: '#312e42'
  tertiary-container: '#938da6'
  on-tertiary-container: '#2a273b'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#ffdbcf'
  primary-fixed-dim: '#ffb59c'
  on-primary-fixed: '#390c00'
  on-primary-fixed-variant: '#822700'
  secondary-fixed: '#7afd91'
  secondary-fixed-dim: '#5cdf78'
  on-secondary-fixed: '#002108'
  on-secondary-fixed-variant: '#00531f'
  tertiary-fixed: '#e6dffa'
  tertiary-fixed-dim: '#c9c3dd'
  on-tertiary-fixed: '#1c192c'
  on-tertiary-fixed-variant: '#48445a'
  background: '#151024'
  on-background: '#e8defb'
  surface-variant: '#373147'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 64px
    fontWeight: '700'
    lineHeight: 72px
    letterSpacing: -0.025em
  display-lg-mobile:
    fontFamily: Inter
    fontSize: 36px
    fontWeight: '700'
    lineHeight: 44px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 40px
    fontWeight: '700'
    lineHeight: 48px
    letterSpacing: -0.02em
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 28px
    fontWeight: '700'
    lineHeight: 36px
    letterSpacing: -0.015em
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.005em
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
    letterSpacing: 0em
  body-lg-medium:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '500'
    lineHeight: 28px
    letterSpacing: 0em
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
    letterSpacing: 0em
  body-md-medium:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '500'
    lineHeight: 24px
    letterSpacing: 0em
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.02em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  gutter: 1.5rem
  gutter-mobile: 1rem
  margin: 2rem
  margin-mobile: 1rem
  space-xs: 0.5rem
  space-sm: 0.75rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2rem
---

## Brand & Style

This design system drives an enterprise-grade cloud commerce engine engineered for retail, hospitality, and multi-location merchant chains operating across East Africa. It combines the rigorous operational speed of modern developer infrastructure with the reliability demanded by high-volume point-of-sale environments. The aesthetic balances high-density data utility with high-contrast ambient confidence—drawing inspiration from Stripe's precision, Toast's physical durability, and Linear's dark-mode depth.

The brand persona is authoritative, dependable, and swift. It eliminates transactional friction during network handshakes, offline syncing, and carrier payment validations. The visual presentation leverages a deep violet obsidian foundation punctuated by an energetic equatorial coral, creating immediate visual hierarchy, tactical clarity under intense sunlight, and frictionless night-shift operability.

## Colors

The system operates primarily in an immersive, low-strain dark mode, supplemented by an intentional, inverted high-contrast cream light mode reserved for thermal paper emulation, executive print reports, and physical point-of-sale receipt previews.

### Roles & Semantic Mapping
- **Canvas Base (`#0F0A1E`)**: Deep violet-black foundation providing rich depth and stark contrast for active terminal sessions.
- **Surface Elevation (`#1A1430`)**: Raised containers, checkout modules, cart panels, and modal shells.
- **Structural Outlines (`#2A2245`)**: 1px structural borders dividing transactional columns and line items without visual noise.
- **Primary Energy (`#F4622A`)**: Coral-orange designated strictly for critical conversion actions—submitting payments, confirming inventory batch changes, and primary checkout buttons. Transitions to `#FF7A45` on active hover.
- **Ambient Aura (`rgba(244, 98, 42, 0.15)`)**: Radial atmospheric wash focused behind key financial summaries, high-value metrics, and active POS tender displays.
- **East African Integration Touchpoint (`#00A344`)**: Authentic M-Pesa green reserved exclusively for cellular network validation, STK push verification, and instant mobile settlement confirmation.
- **Telemetry Indicators**:
  - Success / Sync Active: Emerald Green (`#10B981`)
  - Hold / Pending Carrier: Amber (`#F59E0B`)
  - Fault / Voided Transaction: Carmine Red (`#EF4444`)
- **Light Contrast Inversion Surface (`#FAF8F5`)**: Used for receipts and operational paper workflows. Paired with primary dark text (`#0F0A1E`) and crisp warm borders (`#E5DFD7`).
- **Typography Scale**: High-contrast white (`#FFFFFF`) for primary numerical and transactional data; cool lavender grey (`#B8B2CC`) for labels, table headers, SKU metadata, and secondary status counters.

## Typography

Typography is strictly driven by `Inter` across all structural breakpoints to ensure pixel-perfect legibility, optical neutral balance, and tabular numeral alignment during high-speed checkout flows.

- **Tabular Figures**: All currency, stock levels, weight measures, and tax calculations must use `font-variant-numeric: tabular-nums` to eliminate layout jitter during active ledger balance changes.
- **Hierarchy Rules**:
  - `display-lg`: Dashboard gross revenue indicators, daily aggregate sales, and primary balance screens.
  - `headline-lg` through `headline-md`: Store catalog partitions, tender stage headers, and transactional modal banners.
  - `body-lg` (Regular / Medium): Terminal item descriptions and line-item totals.
  - `label-md` / `label-sm`: Micro-badges (e.g., KRA E-TIMS verified, network status, SKU hashes, offline cache markers).

## Layout & Spacing

The design system is constructed on a rigid 8px spatial grid, capping wide-screen viewports at a centered `1200px` content boundary.

### Layout Mechanics
- **Grid Architecture**: 12-column fluid grid on desktop (`>1024px`) with `1.5rem` (24px) gutters; collapsing to an 8-column layout on tablets (`768px - 1023px`), and a 4-column structured stack on handheld mobile terminals (`<767px`).
- **POS Dual-Zone Workspace**:
  - **Left Rail / Workspace (60–65% desktop width)**: Multi-category visual catalog, SKU search inputs, and item modifier buttons.
  - **Right Rail / Register (35–40% desktop width)**: Continuous active cart stream, itemized discounts, fiscal tax compliance calculations, and checkout invocation.
- **Form Factor Adaptations**:
  - **Mobile (<768px)**: The right-rail cart converts into a persistent sticky bottom sheet displaying the aggregated balance and an expanded swipe-to-finalize control.

## Elevation & Depth

Visual hierarchy does not rely on generic drop shadows, which muddy dark interfaces. Depth is articulated through three distinct structural layers:

1. **Base Surface Layer (`#0F0A1E`)**: Canvas backdrop housing fixed navigation bars, structural splitters, and background views.
2. **Surface Container Tier (`#1A1430`)**: Elevated structural cards, grid tiles, customer cards, and register pads. Outlined by a crisp `1px solid #2A2245`.
3. **Elevated Floating Layer (`#221B3D`)**: Drawers, flyout menus, and modal dialogs. Employs a multi-stop ambient shadow: `0 20px 40px -15px rgba(0, 0, 0, 0.7), 0 0 0 1px #3B315E`.
4. **Primary Glow Atmospheric Effect**: Elements handling conversion (such as the "Pay Now" action or selected payment methods) project an intentional radial bloom: `box-shadow: 0 0 24px rgba(244, 98, 42, 0.25)`.

## Shapes

The interface balances modern industrial precision with ergonomic touch responsiveness.

- **Standard Cards, Tables, Modals, and Modules**: `12px` border radius (`rounded-md` in custom scaling, anchored to level 2). This creates structural cohesion without appearing cartoonish.
- **Interactive Inputs and Sub-Elements**: `8px` corner radius for standard form inputs, search filters, and numeric keypad keys.
- **Status Pills, Counter Badges, and Pill Selectors**: `9999px` full-capsule radius (`rounded-full`), clearly segregating operational metadata from actionable squared cards.
- **Vector Icons**: Linear 1.5px stroke geometries, sharp terminal points, contained within consistent 20px or 24px bounding boxes.

## Components

### Buttons
- **Primary Action**: Solid `#F4622A` background, pure white `#FFFFFF` text, `font-weight: 600`, `12px` radius. Hover shifts to `#FF7A45` with a subtle orange atmospheric shadow. Active state shifts scale to `0.98`.
- **M-Pesa Tender Action**: Specialized payment button featuring `#00A344` background, white text, and an inline official badge icon.
- **Secondary / Ghost**: Background transparent, border `1px solid #2A2245`, text `#B8B2CC`. Hover activates background to `rgba(255, 255, 255, 0.04)` and text to `#FFFFFF`.

### Inputs & Barcode Fields
- **Container**: `#1A1430` background, `1px solid #2A2245`, `8px` corner radius. Font color `#FFFFFF`, placeholder `#B8B2CC`.
- **Focus**: Border highlights instantly to `#F4622A` with an ambient glow ring: `0 0 0 3px rgba(244, 98, 42, 0.15)`.
- **Integrated Hardware Scanners**: Active barcode input fields display a dynamic, pulsing scanline icon on the right edge.

### Status Chips & Badges
- **Structure**: `rounded-full` (9999px), `12px` typography, `padding: 4px 10px`, medium weight.
- **Variants**:
  - `Online / Synced`: Background `rgba(16, 185, 129, 0.12)`, text `#10B981`, border `1px solid rgba(16, 185, 129, 0.25)`. Includes an active 6px radial dot.
  - `M-Pesa Verified`: Background `rgba(0, 163, 68, 0.12)`, text `#00A344`, border `1px solid rgba(0, 163, 68, 0.25)`.
  - `Offline / Pending Sync`: Background `rgba(245, 158, 11, 0.12)`, text `#F59E0B`, border `1px solid rgba(245, 158, 11, 0.25)`.

### Lists & Cart Streams
- **Item Rows**: Divided by hairline `1px solid #2A2245` rules. High-contrast white for item label and total line amount; lavender-grey `#B8B2CC` for unit prices, modifiers, and SKU identifiers.
- **Swipe Actions (Mobile)**: Lateral swipe reveals a destructive vermilion red discard trigger.

### Checkboxes & Radios
- Box size `18px`, `4px` radius for checkboxes, full circle for radio buttons.
- Border `1.5px solid #2A2245`. Checked state fills `#F4622A` with white micro check icon.

### Cards & POS Keypads
- Raised cards use `#1A1430` surface with `1px solid #2A2245` border and `12px` border-radius.
- Keypad cells feature minimum touch targets of `48px x 48px` with tactile feedback and high-contrast numbers for rapid manual price adjustments.