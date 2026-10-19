{{--
    Sistema de diseño NOC — tokens en tres capas:
      1. Primitivos: valores crudos (escalas de color, radios, sombras, tiempos).
      2. Semánticos: propósito (fondo, superficie, texto, primario, estados); cambian en modo oscuro.
      3. Componentes: tarjeta, botón, campo, barra lateral, vidrio y metálicos.
    Las vistas deben usar las capas 2 y 3 (var(--nm-…) o las clases nm-*), nunca hex sueltos.
    La misma escala de color está en tailwind.config (layouts/app) y en resources/css/app.css.
--}}
<style>
    :root {
        /* ===== 1. PRIMITIVOS ===== */
        /* Azul real (referencia Azia) */
        --nm-azure-50: #eef5ff;  --nm-azure-100: #d9e8ff; --nm-azure-200: #b3d1ff; --nm-azure-300: #80b3ff;
        --nm-azure-400: #4d94fd; --nm-azure-500: #1a7afb; --nm-azure-600: #0168fa; --nm-azure-700: #0153c7;
        --nm-azure-800: #023f99; --nm-azure-900: #03306f; --nm-azure-950: #031d45;
        /* Marino (tarjetas destacadas y barra lateral) */
        --nm-navy-50: #eef2fb;   --nm-navy-100: #d6e0f5;  --nm-navy-200: #aebfe9;  --nm-navy-300: #7e97d6;
        --nm-navy-400: #4f6fc0;  --nm-navy-500: #2c4fa6;  --nm-navy-600: #1a3d8f;  --nm-navy-700: #123178;
        --nm-navy-800: #0d2660;  --nm-navy-900: #0a1d4a;  --nm-navy-950: #060f2b;
        /* Neutros fríos */
        --nm-neutral-0: #ffffff;   --nm-neutral-50: #f6f8fb;  --nm-neutral-100: #eef1f6; --nm-neutral-200: #e3e7ed;
        --nm-neutral-300: #cdd4e0; --nm-neutral-400: #97a3b9; --nm-neutral-500: #637087; --nm-neutral-700: #3b4863;
        --nm-neutral-900: #1c273c; --nm-neutral-950: #0b1220;
        /* Institucionales (logotipo de la Secretaría de la Hacienda Pública) */
        --nm-inst-azul: #5b7d95;
        --nm-inst-naranja: #f07f12;
        /* Estados */
        --nm-green-500: #10b981; --nm-amber-500: #f59e0b; --nm-red-500: #e5383b; --nm-teal-500: #00b8c4;

        --nm-radius-xs: 6px; --nm-radius-sm: 8px; --nm-radius-md: 10px; --nm-radius-lg: 12px; --nm-radius-xl: 14px;
        --nm-shadow-sm: 0 1px 2px rgb(16 24 40 / 0.06);
        --nm-shadow-md: 0 1px 2px rgb(16 24 40 / 0.05), 0 6px 16px -8px rgb(16 24 40 / 0.14);
        --nm-shadow-lg: 0 2px 4px rgb(16 24 40 / 0.06), 0 16px 32px -12px rgb(16 24 40 / 0.22);
        --nm-dur-fast: 150ms; --nm-dur-base: 220ms; --nm-dur-slow: 360ms;
        --nm-ease: cubic-bezier(0.2, 0.7, 0.2, 1);

        /* ===== 2. SEMÁNTICOS (tema claro) ===== */
        --nm-primary-rgb: 1 104 250;               /* azure-600 */
        --nm-primary: rgb(var(--nm-primary-rgb));
        --nm-primary-hover: var(--nm-azure-700);
        --nm-primary-2: var(--nm-navy-600);        /* segundo tono de degradados y gráficas */
        --nm-on-primary: #ffffff;
        --nm-bg: var(--nm-neutral-200);
        --nm-surface-rgb: 255 255 255;
        --nm-sunken-rgb: 246 248 251;          /* paneles hundidos dentro de una tarjeta */
        --nm-surface: rgb(var(--nm-surface-rgb));
        --nm-ink-rgb: 10 18 32;                /* siempre oscuro: terminal, chasis, paneles de equipo */
        --nm-ink: rgb(var(--nm-ink-rgb));
        --nm-sunken: rgb(var(--nm-sunken-rgb));
        --nm-surface-2: var(--nm-neutral-50);
        --nm-border: #d8dee8;
        --nm-border-strong: var(--nm-neutral-300);
        --nm-text: var(--nm-neutral-900);
        --nm-text-muted: var(--nm-neutral-500);     /* 4.9:1 sobre blanco */
        --nm-success: var(--nm-green-500);
        --nm-warning: var(--nm-amber-500);
        --nm-danger: var(--nm-red-500);
        --nm-info: var(--nm-teal-500);
        --nm-ring: rgb(var(--nm-primary-rgb) / 0.55);

        /* ===== 3. COMPONENTES ===== */
        --nm-card-bg: var(--nm-surface);
        --nm-card-border: var(--nm-border);
        --nm-card-radius: var(--nm-radius-lg);
        --nm-card-shadow: var(--nm-shadow-md);
        --nm-card-shadow-hover: var(--nm-shadow-lg);
        --nm-btn-radius: var(--nm-radius-sm);
        --nm-input-radius: var(--nm-radius-sm);
        --nm-input-border: var(--nm-border-strong);
        --nm-sidebar-bg: linear-gradient(170deg, var(--nm-navy-800) 0%, var(--nm-navy-600) 58%, var(--nm-azure-700) 130%);
        --nm-sidebar-fg: var(--nm-azure-100);
        --nm-glass-bg: rgb(255 255 255 / 0.72);
        --nm-glass-border: rgb(255 255 255 / 0.65);
        --nm-glass-blur: blur(14px) saturate(150%);
        --nm-metal-navy: linear-gradient(135deg, var(--nm-navy-800) 0%, var(--nm-navy-600) 48%, var(--nm-azure-700) 100%);
        --nm-metal-azure: linear-gradient(135deg, var(--nm-azure-700) 0%, var(--nm-azure-600) 50%, var(--nm-azure-400) 100%);
        --nm-metal-silver: linear-gradient(180deg, #ffffff 0%, #eef1f6 55%, #dfe4ec 100%);
        --nm-metal-sheen: linear-gradient(115deg, rgb(255 255 255 / 0.22) 0%, rgb(255 255 255 / 0) 38%, rgb(255 255 255 / 0) 62%, rgb(255 255 255 / 0.08) 100%);
    }

    /* ===== 2. SEMÁNTICOS (tema oscuro) ===== */
    .dark {
        --nm-primary-rgb: 47 124 248;               /* #2f7cf8: 4.8:1 sobre la superficie oscura */
        --nm-primary-hover: var(--nm-azure-400);
        --nm-primary-2: var(--nm-azure-300);
        --nm-bg: #070b14;
        --nm-surface-rgb: 13 19 32;            /* #0d1320 */
        --nm-sunken-rgb: 9 14 24;              /* #090e18 */
        --nm-surface-2: #111a2b;
        --nm-border: rgb(71 85 105 / 0.42);
        --nm-border-strong: rgb(100 116 139 / 0.55);
        --nm-text: #e8edf5;
        --nm-text-muted: #9aa8bf;
        --nm-card-shadow: none;
        --nm-card-shadow-hover: 0 0 0 1px rgb(var(--nm-primary-rgb) / 0.25);
        --nm-sidebar-bg: linear-gradient(170deg, #0a1222 0%, #0d1830 60%, #0f2147 130%);
        --nm-glass-bg: rgb(13 19 32 / 0.68);
        --nm-glass-border: rgb(148 163 184 / 0.16);
        --nm-metal-silver: linear-gradient(180deg, #1a2333 0%, #131b29 100%);
    }

    /* ===== Clases de componente ===== */
    body { background-color: var(--nm-bg); color: var(--nm-text); }

    .floating-card {
        background: var(--nm-card-bg);
        border: 1px solid var(--nm-card-border);
        border-radius: var(--nm-card-radius);
        box-shadow: var(--nm-card-shadow);
        transition: box-shadow var(--nm-dur-base) var(--nm-ease), border-color var(--nm-dur-base) ease, background-color var(--nm-dur-base) ease;
    }
    /* Solo se eleva lo que se puede pulsar: una tarjeta informativa que "salta" sugiere un clic que no existe */
    a.floating-card:hover, button.floating-card:hover, .floating-card.nm-interactiva:hover {
        box-shadow: var(--nm-card-shadow-hover);
        border-color: rgb(var(--nm-primary-rgb) / 0.35);
        transform: translateY(-1px);
    }

    /* Vidrio: barras flotantes, menús emergentes y banners sobre contenido */
    .nm-glass {
        background: var(--nm-glass-bg) !important;
        -webkit-backdrop-filter: var(--nm-glass-blur);
        backdrop-filter: var(--nm-glass-blur);
        border: 1px solid var(--nm-glass-border);
    }

    /* Metálicos: solo para superficies de marca y KPI destacados (texto siempre blanco) */
    /* El brillo va como primera capa del fondo para no cubrir el contenido */
    .nm-metal-navy { background: var(--nm-metal-sheen), var(--nm-metal-navy); color: #fff; }
    .nm-metal-azure { background: var(--nm-metal-sheen), var(--nm-metal-azure); color: #fff; }
    .nm-metal-silver { background: var(--nm-metal-silver); border: 1px solid var(--nm-border); }
    .floating-card.nm-metal-navy, .floating-card.nm-metal-azure { border-color: transparent; }

    .nm-sidebar { background: var(--nm-metal-sheen), var(--nm-sidebar-bg) !important; }

    /* Interacción */
    :focus-visible { outline: 2px solid var(--nm-ring); outline-offset: 2px; }
    button:not(:disabled), [role="button"], summary, select, label[for], input[type="checkbox"], input[type="radio"] { cursor: pointer; }
    button:disabled, [aria-disabled="true"] { cursor: not-allowed; opacity: 0.55; }
    ::selection { background: rgb(var(--nm-primary-rgb) / 0.85); color: #fff; }
</style>
