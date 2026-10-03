<style>
    .pp-doc { color:#3a2a4a; }
    .pp-doc-title {
        display:flex; align-items:center; gap:.75rem;
        font-size:.8rem; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#7b68ee;
        margin-bottom:.25rem;
    }
    .pp-doc-title::after { content:""; flex:1; height:2px; border-radius:2px; background:linear-gradient(90deg, #7b68ee, rgba(123,104,238,0)); }
    .pp-doc-meta {
        display:inline-flex; align-items:center; gap:.45rem; margin:.9rem 0 1.25rem;
        background:#f6ecff; color:#7b2d8b; border:1px solid #ead9ff;
        border-radius:50px; padding:.35rem .9rem; font-size:.8rem; font-weight:600;
    }
    .pp-heading {
        display:flex; align-items:center; gap:.75rem; margin:2rem 0 .9rem;
        font-family:'Fredoka', sans-serif; font-size:1.22rem; font-weight:700; color:#241553;
        scroll-margin-top:90px;
    }
    .pp-heading:first-of-type { margin-top:.5rem; }
    .pp-heading-num {
        width:34px; height:34px; flex:0 0 34px; border-radius:50%;
        display:flex; align-items:center; justify-content:center;
        background:linear-gradient(135deg, var(--kid-pink), var(--kid-purple));
        color:#fff; font-size:.95rem; box-shadow:0 6px 14px rgba(155,93,229,.35);
    }
    .pp-subheading {
        font-family:'Fredoka', sans-serif; font-size:1.02rem; font-weight:600; color:#5b3fa8;
        margin:1.4rem 0 .6rem;
    }
    .pp-p { line-height:1.8; margin-bottom:.85rem; color:#3a2a4a; }
    .pp-list { list-style:none; padding:0; margin:0 0 1.1rem; }
    .pp-list li {
        position:relative; padding:.6rem .95rem .6rem 2.5rem; margin-bottom:.5rem;
        border:1px solid #eee8ff; background:#fbfaff; border-radius:12px; line-height:1.65;
    }
    .pp-list li::before {
        content:""; position:absolute; left:1rem; top:1.05rem;
        width:8px; height:8px; border-radius:50%; background:var(--kid-pink);
        box-shadow:0 0 0 4px rgba(255,111,163,.18);
    }
    .pp-toc { position:sticky; top:90px; }
    .pp-toc-title { display:flex; align-items:center; gap:.5rem; font-family:'Fredoka', sans-serif; font-weight:700; color:#241553; margin-bottom:.75rem; }
    .pp-toc-title i { color:var(--kid-pink); }
    .pp-toc-list { list-style:none; margin:0; padding:0; counter-reset:toc; }
    .pp-toc-list li + li { margin-top:.15rem; }
    .pp-toc-list a {
        display:flex; align-items:center; gap:.6rem; padding:.5rem .7rem; border-radius:12px;
        color:#3a2a4a; text-decoration:none; font-size:.92rem; line-height:1.4;
        transition:background .15s ease, color .15s ease;
    }
    .pp-toc-list a:hover, .pp-toc-list a:focus { background:#f6ecff; color:#7b2d8b; }
    .pp-toc-num {
        width:24px; height:24px; flex:0 0 24px; border-radius:50%;
        display:flex; align-items:center; justify-content:center;
        background:#eef4ff; color:#1d4ed8; font-size:.75rem; font-weight:700;
    }
    .pp-toc-list a:hover .pp-toc-num { background:#fff; }
    @media (max-width: 991.98px) { .pp-toc { position:static; } }
</style>
