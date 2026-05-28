# Chat: Button style name inquiry

- **Datum**: 2026-05-28 15:43
- **Sessie-ID**: `5a99f6f4-b37b-47ef-8030-8a199df501dc`
- **Berichten**: 7

---

## 🧑 User

https://commonground.nl/groups/view/52ec3ddf-23e5-4746-aae2-5fe2f654f927/common-ground-publicatiesite/wiki/view/4c7d1d11-3a7e-431d-bf03-5380c9c16e95/common-ground



kun je van deze pagina achterhalen wat de style naam is van een gewone button, bijv. "Maak aan" ?

---

## 🤖 Assistant



*Fetched https://commonground.nl/groups/view/52ec3ddf-23e5-4746-aae2-5fe2f654f927/common-ground-publicatiesite/wiki/view/4c7d1d11-3a7e-431d-bf03-5380c9c16e95/common-ground*



---

## 🤖 Assistant



*Fetched https://commonground.nl/groups/view/52ec3ddf-23e5-4746-aae2-5fe2f654f927/common-ground-publicatiesite/wiki/view/4c7d1d11-3a7e-431d-bf03-5380c9c16e95/common-ground*

De pagina bevat geen specifieke CSS-klassenamen voor buttons in de platte tekstinhoud. Laat me de paginabron bekijken via een screenshot of de HTML-broncode. 

*Opened [Browser](vscode-browser:/29b61c0a-656a-491d-8c44-f05ee507e0d1?vscodeLinkType=browser) · Ran Playwright code · Ran Playwright code*



*Ran Playwright code*



---

## 🧑 User

"login om lig te worden" is ook goed

---

## 🤖 Assistant

De "Log in om lid te worden" knop (donkerblauw gevuld) gebruikt de styled-component **`Button`** met een **primary** variant. De gegenereerde CSS-klasse is `erJbxY` en de stijl-eigenschappen zijn:

| Eigenschap | Waarde |
|---|---|
| `background-color` | `rgb(20, 52, 98)` (donkerblauw) |
| `color` | `rgb(255, 255, 255)` |
| `font-family` | `"Rijksoverheid Sans", sans-serif` |
| `font-weight` | `bold` |
| `border-radius` | `6px` |
| `height` | `32px` |
| `padding` | `0 10px` |
| `font-size` | `0.875rem` |

Er zijn twee groottes: `erJbxY` (medium, 32px) en `flabyu` (small, 24px/4px radius). De component heet intern `Button__StyledButton` — dus de React-componentnaam is **`Button`**, vermoedelijk met `type="primary"` of `variant="primary"`.

---

## 🧑 User

Ik wil een stukje statische html intypen in een widget, dus kan geen js gebruiken, maar wel een css style. Hoe kan ik dan een eigen link deze stijl geven?

---

## 🤖 Assistant

Met inline CSS of een `<style>`-blok. Hier zijn beide opties:

**Optie 1 – inline stijl (makkelijkst in een widget):**
```html
<a href="/jouw-link" style="
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 32px;
  padding: 0 10px;
  background-color: rgb(20, 52, 98);
  color: rgb(255, 255, 255);
  font-family: 'Rijksoverheid Sans', sans-serif;
  font-weight: bold;
  font-size: 0.875rem;
  border-radius: 6px;
  border: 1px solid transparent;
  text-decoration: none;
  white-space: nowrap;
">Maak aan</a>
```

**Optie 2 – met `<style>`-blok (netter als je meerdere buttons hebt):**
```html
<style>
  .btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 32px;
    padding: 0 10px;
    background-color: rgb(20, 52, 98);
    color: #fff;
    font-family: 'Rijksoverheid Sans', sans-serif;
    font-weight: bold;
    font-size: 0.875rem;
    border-radius: 6px;
    border: 1px solid transparent;
    text-decoration: none;
    white-space: nowrap;
  }
  .btn-primary:hover { background-color: rgb(23, 59, 111); }
  .btn-primary:active { background-color: rgb(25, 65, 123); }
</style>

<a href="/jouw-link" class="btn-primary">Maak aan</a>
```

Als het widget de `<style>`-tag niet toestaat, gebruik dan optie 1 (inline). De `:hover`/`:active` effecten werken alleen met de `<style>`-variant.

---
