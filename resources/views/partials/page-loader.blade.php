<div class="page-loader" id="page-loader" hidden>
    <div class="page-loader__bar" aria-hidden="true"></div>
    <div class="page-loader__content" role="status" aria-live="polite">
        <div class="page-loader__spinner-wrap">
            <svg viewBox="0 0 50 50" class="page-loader__spinner">
                <circle cx="25" cy="25" r="20" fill="none" stroke-width="4" stroke="#e8ecf0"/>
                <circle cx="25" cy="25" r="20" fill="none" stroke-width="4" stroke="url(#loader-gradient)" stroke-dasharray="90" stroke-dashoffset="60" stroke-linecap="round" class="page-loader__spinner-path"/>
                <defs>
                    <linearGradient id="loader-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="var(--color-primary)"/>
                        <stop offset="100%" stop-color="var(--color-accent)"/>
                    </linearGradient>
                </defs>
            </svg>
        </div>
        <div class="page-loader__info">
            <span class="page-loader__text">Carregando</span>
            <span class="page-loader__dots"><span>.</span><span>.</span><span>.</span></span>
        </div>
    </div>
</div>
