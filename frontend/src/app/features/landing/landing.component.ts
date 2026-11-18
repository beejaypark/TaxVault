import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';

import { BrandLogoComponent } from '../../shared/components/brand-logo/brand-logo.component';

@Component({
  selector: 'app-landing',
  imports: [RouterLink, BrandLogoComponent],
  template: `
    <main class="landing-page">
      <nav class="landing-nav" aria-label="Main navigation">
        <tv-brand-logo [size]="34" />
        <a class="nav-login" routerLink="/login">Log in</a>
      </nav>

      <section class="hero" aria-labelledby="hero-title">
        <div class="hero-copy">
          <p class="eyebrow">Tax records, without the scramble</p>
          <h1 id="hero-title">Your tax documents, ready when you are.</h1>
          <p class="hero-intro">TaxVault brings receipts, statements and tax evidence into one calm workspace, so you can find what matters and hand over a clearer record at tax time.</p>
          <div class="hero-actions">
            <a class="primary-action" routerLink="/login">Get started</a>
            <a class="secondary-action" href="#how-it-works">See how it works</a>
          </div>
          <p class="hero-note">Secure storage, OCR-assisted capture and review tools in one place</p>
        </div>

        <div class="hero-preview" aria-label="TaxVault document workspace preview">
          <div class="preview-topline"><span class="preview-dot"></span><span>TaxVault workspace</span><span class="preview-year">FY 2026–27</span></div>
          <div class="preview-heading"><div><span class="preview-label">Document overview</span><strong>Everything in place</strong></div><span class="preview-status">5 documents</span></div>
          <div class="preview-progress"><span></span></div>
          <div class="preview-list">
            <div class="preview-row"><span class="file-mark">PDF</span><div><strong>Receipt · Q1</strong><small>Issue date · 01 Sep 2026</small></div><span class="reviewed">Reviewed</span></div>
            <div class="preview-row"><span class="file-mark file-mark-blue">PDF</span><div><strong>Brisbane utilities</strong><small>Imported from OneDrive</small></div><span class="pending">Review</span></div>
            <div class="preview-row"><span class="file-mark file-mark-warm">IMG</span><div><strong>Work expense</strong><small>OCR details ready</small></div><span class="pending">Review</span></div>
          </div>
          <div class="preview-footer"><span>Last synced just now</span><span class="sync-icon">↗</span></div>
        </div>
      </section>

      <section id="how-it-works" class="benefits" aria-labelledby="benefits-title">
        <div class="section-heading"><p class="eyebrow">A clearer tax season</p><h2 id="benefits-title">Keep the evidence together</h2><p>TaxVault helps you move from scattered files to a reviewable record.</p></div>
        <div class="benefit-grid">
          <article class="benefit-card"><span class="benefit-number">01</span><h3>Bring documents in</h3><p>Upload files in bulk or import folders from connected storage such as OneDrive.</p></article>
          <article class="benefit-card benefit-card-accent"><span class="benefit-number">02</span><h3>Find the important details</h3><p>OCR-assisted extraction surfaces dates and amounts, with manual correction when a document needs a human eye.</p></article>
          <article class="benefit-card"><span class="benefit-number">03</span><h3>Review with confidence</h3><p>Keep source documents, corrections and review history connected for you and your accountant.</p></article>
        </div>
      </section>

      <footer class="landing-footer"><tv-brand-logo [size]="28" /><span>Private by default. Clear by design.</span><a routerLink="/login">Log in to TaxVault</a></footer>
    </main>
  `,
  styles: [`
    :host { display: block; min-height: 100vh; }
    .landing-page { min-height: 100vh; color: #172b4d; background: #f6f8fb; }
    .landing-nav { display: flex; align-items: center; justify-content: space-between; max-width: 1180px; margin: 0 auto; padding: 28px 32px; }
    .nav-login, .landing-footer a { color: #123f78; font-size: 14px; font-weight: 600; text-decoration: none; }
    .nav-login:hover, .landing-footer a:hover { text-decoration: underline; }
    .hero { display: grid; grid-template-columns: minmax(0, .92fr) minmax(440px, 1.08fr); align-items: center; gap: 72px; max-width: 1180px; margin: 0 auto; padding: 76px 32px 112px; }
    .hero-copy { max-width: 520px; }
    .eyebrow { margin: 0 0 16px; color: #1f7ac4; font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    h1 { max-width: 560px; margin: 0; color: #12345d; font-size: clamp(42px, 5vw, 68px); font-weight: 500; letter-spacing: -.055em; line-height: 1.02; }
    .hero-intro { max-width: 500px; margin: 24px 0 0; color: #58708f; font-size: 17px; line-height: 1.65; }
    .hero-actions { display: flex; align-items: center; gap: 16px; margin-top: 32px; }
    .primary-action, .secondary-action { display: inline-flex; align-items: center; justify-content: center; min-height: 46px; padding: 0 20px; border-radius: 8px; font-size: 14px; font-weight: 700; text-decoration: none; }
    .primary-action { background: #123f78; color: white; box-shadow: 0 8px 18px rgb(18 63 120 / 16%); }
    .primary-action:hover { background: #0c315e; }
    .secondary-action { border: 1px solid #cbd8e6; color: #123f78; background: transparent; }
    .secondary-action:hover { background: white; }
    .hero-note { margin: 20px 0 0; color: #8091a7; font-size: 12px; }
    .hero-preview { padding: 20px; border: 1px solid #d9e2ed; border-radius: 18px; background: white; box-shadow: 0 24px 60px rgb(31 63 101 / 12%); transform: rotate(1.2deg); }
    .preview-topline, .preview-footer, .preview-heading, .preview-row { display: flex; align-items: center; }
    .preview-topline { gap: 8px; color: #8392a7; font-size: 11px; }
    .preview-dot { width: 8px; height: 8px; border-radius: 50%; background: #2c9a67; }
    .preview-year { margin-left: auto; color: #50749e; }
    .preview-heading { justify-content: space-between; margin-top: 34px; }
    .preview-heading > div { display: grid; gap: 8px; }
    .preview-label { color: #7b8ca1; font-size: 11px; }
    .preview-heading strong { color: #18385f; font-size: 24px; font-weight: 500; }
    .preview-status { padding: 7px 10px; border-radius: 6px; background: #edf5fd; color: #1f7ac4; font-size: 11px; font-weight: 700; }
    .preview-progress { height: 6px; margin: 24px 0; border-radius: 4px; background: #edf1f5; overflow: hidden; }
    .preview-progress span { display: block; width: 72%; height: 100%; border-radius: inherit; background: #2c9a67; }
    .preview-list { display: grid; gap: 8px; }
    .preview-row { gap: 12px; min-height: 64px; padding: 0 12px; border: 1px solid #e8edf2; border-radius: 9px; }
    .preview-row > div { display: grid; gap: 5px; min-width: 0; }
    .preview-row strong { color: #294563; font-size: 13px; font-weight: 600; }
    .preview-row small { color: #8695a7; font-size: 11px; }
    .file-mark { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 8px; background: #e9f7ef; color: #2c9a67; font-size: 9px; font-weight: 800; }
    .file-mark-blue { background: #edf5fd; color: #1f7ac4; }
    .file-mark-warm { background: #fff5df; color: #a76c08; }
    .reviewed, .pending { margin-left: auto; color: #2c9a67; font-size: 11px; font-weight: 700; }
    .pending { color: #a76c08; }
    .preview-footer { justify-content: space-between; margin-top: 18px; color: #9aa7b7; font-size: 11px; }
    .sync-icon { color: #2c9a67; font-size: 18px; }
    .benefits { max-width: 1180px; margin: 0 auto; padding: 0 32px 100px; }
    .section-heading { max-width: 520px; }
    .section-heading h2 { margin: 0; color: #18385f; font-size: 34px; font-weight: 500; letter-spacing: -.03em; }
    .section-heading > p:last-child { margin: 12px 0 0; color: #687e97; font-size: 15px; }
    .benefit-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 32px; }
    .benefit-card { min-height: 190px; padding: 24px; border: 1px solid #dce5ee; border-radius: 12px; background: white; }
    .benefit-card-accent { border-color: #b9d4ec; background: #eef6fd; }
    .benefit-number { color: #1f7ac4; font-size: 12px; font-weight: 700; }
    .benefit-card h3 { margin: 28px 0 10px; color: #234466; font-size: 17px; font-weight: 600; }
    .benefit-card p { margin: 0; color: #6d8197; font-size: 13px; line-height: 1.6; }
    .landing-footer { display: flex; align-items: center; gap: 20px; max-width: 1180px; margin: 0 auto; padding: 24px 32px 32px; border-top: 1px solid #dce5ee; color: #8292a5; font-size: 12px; }
    .landing-footer span { flex: 1; }
    @media (max-width: 850px) { .hero { grid-template-columns: 1fr; gap: 48px; padding-top: 48px; } .hero-copy { max-width: 640px; } .hero-preview { max-width: 620px; } }
    @media (max-width: 620px) { .landing-nav, .hero, .benefits, .landing-footer { padding-left: 20px; padding-right: 20px; } .hero { padding-top: 32px; padding-bottom: 72px; } h1 { font-size: 44px; } .benefit-grid { grid-template-columns: 1fr; } .landing-footer { align-items: flex-start; flex-wrap: wrap; gap: 12px; } .landing-footer span { flex-basis: 100%; order: 3; } }
  `],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LandingComponent {}
