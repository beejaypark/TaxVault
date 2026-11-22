import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { afterNextRender, ChangeDetectionStrategy, Component, DestroyRef, ElementRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs';
import { BrandLogoComponent } from '../../shared/components/brand-logo/brand-logo.component';
import { LoginComponent } from '../auth/pages/login/login.component';
import { ProductChatComponent } from './product-chat.component';

@Component({
  selector: 'app-landing',
  imports: [BrandLogoComponent, LoginComponent, ProductChatComponent],
  template: `
    <main class="landing-page">
      <header class="site-header">
        <nav class="site-nav" aria-label="Primary navigation">
          <a class="brand" href="#top" aria-label="TaxVault home"><tv-brand-logo [size]="42" /></a>
          <div class="nav-links">
            <a href="#how-it-works">How it works</a>
            <a href="#benefits">Why TaxVault</a>
            <a href="#contact">Contact us</a>
          </div>
          <div class="nav-actions">
            <button type="button" class="updates" (click)="openTrial()">Get free tax updates</button>
            <button type="button" class="login-link" (click)="openLogin()">Log in</button>
          </div>
        </nav>
      </header>
      <section class="hero-scene" id="top">
        <div class="hero">
          <div class="copy">
            <h1>Your tax documents,<br class="hero-break" /> <em>ready when you are</em></h1>
            <p>Bring receipts, statements and tax evidence together. Review the details in one place and share a clearer record with your accountant.</p>
            <div class="hero-actions">
              <button type="button" class="primary" (click)="openLogin()">Get started</button>
              <a href="#how-it-works">See how it works <span aria-hidden="true">↓</span></a>
            </div>
          </div>
          <figure class="preview" aria-labelledby="example-title">
            <figcaption class="preview-top"><span>Example workspace</span><span>FY 2026–27</span></figcaption>
            <h2 id="example-title">Document review</h2>
            <div class="review-example">
              <section class="example-source" aria-labelledby="example-source-title">
                <h3 id="example-source-title">Source document</h3>
                <div class="sample-receipt">
                  <strong>{{ receipt.supplier }}</strong>
                  <time datetime="2026-09-01">{{ receipt.date }}</time>
                  <dl>
                    <div><dt>Printer paper</dt><dd>$24.40</dd></div>
                    <div><dt>Ink cartridge</dt><dd>$62.00</dd></div>
                    <div class="receipt-total"><dt>Total</dt><dd>{{ receipt.amount }}</dd></div>
                  </dl>
                </div>
              </section>
              <section class="example-fields" aria-labelledby="example-fields-title">
                <h3 id="example-fields-title">Extracted details</h3>
                <dl>
                  <div><dt>Supplier</dt><dd>{{ receipt.supplier }}</dd></div>
                  <div><dt>Issue date</dt><dd>{{ receipt.date }}</dd></div>
                  <div><dt>Total amount</dt><dd>{{ receipt.amount }}</dd></div>
                </dl>
                <span class="example-status">✓ Reviewed</span>
              </section>
            </div>
          </figure>
        </div>
      </section>
      <section class="pricing-section" id="pricing" aria-labelledby="pricing-title">
        <div class="pricing-heading"><p class="eyebrow">Simple pricing</p><h2 id="pricing-title">Choose how you want to start</h2><p>Choose monthly or annual billing. Your card is securely collected by Stripe, and the 30-day trial is available on both plans.</p></div>
        <div class="pricing-grid">
          <article class="pricing-card featured" [class.selected]="selectedPricingPlan === 'trial'" (mouseenter)="selectedPricingPlan = 'trial'" (focusin)="selectedPricingPlan = 'trial'">
            <span class="pricing-label">Monthly plan</span><h3>30-day free trial</h3><strong>A$7.99 <small>/ month</small></strong>
            <p>Try every feature for 30 days. Add your card now; automatic billing begins after the trial unless cancelled.</p>
            <button type="button" class="primary" (click)="openPricingSignup('trial')">Start 30-day free trial</button>
          </article>
          <article class="pricing-card annual-card" [class.selected]="selectedPricingPlan === 'yearly'" (mouseenter)="selectedPricingPlan = 'yearly'" (focusin)="selectedPricingPlan = 'yearly'">
            <span class="pricing-label">Best value</span><h3>Annual plan</h3><strong>A$79.90 <small>/ year</small></strong>
            <p>Save compared with monthly billing. Your card is collected securely and the annual plan starts after your free month.</p>
            <button type="button" class="primary" (click)="openPricingSignup('yearly')">Choose annual plan</button>
          </article>
        </div>
        <p class="pricing-note">Cancel anytime before the next billing date. No long-term contract. Payments are securely processed by Stripe.</p>
      </section>
      <section class="benefits" id="benefits"><p class="eyebrow">A clearer tax season</p><h2>Keep the evidence together</h2><div class="benefit-grid"><article><b>01</b><h3>Bring documents in</h3><p>Upload files in bulk or import folders from connected storage.</p></article><article class="accent"><b>02</b><h3>Find the important details</h3><p>OCR-assisted extraction surfaces dates and amounts for review.</p></article><article><b>03</b><h3>Review with confidence</h3><p>Keep source documents, corrections and review history connected.</p></article></div></section>
      <section class="fit-section" aria-labelledby="fit-title">
        <div class="section-intro">
          <div class="fit-heading"><p class="eyebrow">Made for your tax workflow</p><h2 id="fit-title">Who is TaxVault for?</h2><p>TaxVault is useful when tax evidence arrives from more than one place and you want a clearer record before lodgement.</p></div>
          <img class="section-photo" src="assets/branding/taxvault-audience.jpg" width="1672" height="941" loading="lazy" decoding="async" alt="Two people sorting receipts and tax paperwork at a table" />
        </div>
        <div class="fit-grid"><article><span class="fit-icon">01</span><h3>Individuals and families</h3><p>Keep receipts, statements and property documents together throughout the year.</p></article><article><span class="fit-icon">02</span><h3>Sole traders and small businesses</h3><p>Bring work expenses and business records into one reviewable workspace.</p></article><article><span class="fit-icon">03</span><h3>People working with an accountant</h3><p>Share a more complete, traceable record with the professional helping you.</p></article></div>
      </section>
      <section class="workspace-section" aria-labelledby="workspace-title"><div class="workspace-heading"><p class="eyebrow">One workspace, fewer loose ends</p><h2 id="workspace-title">Everything you need to stay tax-ready</h2><p>Each part of TaxVault has a clear job, so you can move from finding a document to understanding your position without jumping between tools.</p></div><div class="workspace-grid"><article><span class="workspace-tag">OVERVIEW</span><h3>Dashboard</h3><p>See your tax readiness, recent activity and important next steps at a glance.</p></article><article><span class="workspace-tag">DOCUMENTS</span><h3>Documents and financial years</h3><p>Keep source files grouped by year, type and storage provider so they are easy to find again.</p></article><article><span class="workspace-tag">REVIEW</span><h3>Reviews and extraction</h3><p>Check OCR results, correct missing details and keep a history of what changed.</p></article><article><span class="workspace-tag">MONEY</span><h3>Income, expenses and assets</h3><p>Connect transactions, properties and investments to the evidence that supports them.</p></article><article><span class="workspace-tag">REPORTS</span><h3>Tax news and tax return pack</h3><p>Stay informed and prepare a clearer summary to use when it is time to lodge.</p></article><article><span class="workspace-tag">COLLABORATE</span><h3>Accountant sharing and settings</h3><p>Give an accountant the right access and control storage, notifications and preferences in one place.</p></article></div></section>
      <section class="flow-section" id="how-it-works" aria-labelledby="flow-title">
        <div class="section-intro">
          <div class="flow-heading">
            <p class="eyebrow">How it works</p>
            <h2 id="flow-title">From receipt to tax record</h2>
            <p>Follow the {{ receipt.supplier }} receipt through TaxVault.</p>
          </div>
          <img class="section-photo" src="assets/branding/taxvault-receipt-review.jpg" width="1672" height="941" loading="lazy" decoding="async" alt="A person comparing a paper receipt with document details on a laptop" />
        </div>
        <div class="process-flow" role="list">
          <div class="process-node" role="listitem"><span>1</span><strong>Upload the receipt</strong><small>Add the original PDF to Documents.</small></div>
          <span class="process-arrow" aria-hidden="true">→</span>
          <div class="process-node" role="listitem"><span>2</span><strong>Extract the details</strong><small>TaxVault finds the supplier, issue date and {{ receipt.amount }} total.</small></div>
          <span class="process-arrow" aria-hidden="true">→</span>
          <div class="process-node" role="listitem"><span>3</span><strong>Check the original</strong><small>Compare the extracted details with the PDF and correct anything that’s off.</small></div>
          <span class="process-arrow" aria-hidden="true">→</span>
          <div class="process-node complete" role="listitem"><span>✓</span><strong>Save the record</strong><small>Record the {{ receipt.amount }} expense with the receipt attached.</small></div>
        </div>
        <div class="accountant-branch"><span class="branch-line" aria-hidden="true">↘</span><div><small>Optional</small><strong>Share with your accountant</strong><p>Include the record and its supporting receipt in an accountant pack.</p></div></div>
      </section>
      <section class="trust-strip"><div><p class="eyebrow">Built for real tax records</p><h2>Simple enough to use every week.</h2></div><p>TaxVault keeps your original files, extracted details and review history connected, so you always know where a number came from.</p></section>
      <section class="faq-section" id="faq" aria-labelledby="faq-title">
        <div>
          <h2 id="faq-title">Frequently asked questions</h2>
          <button #faqChatButton type="button" class="faq-chat" (click)="productChat.openChat(faqChatButton)">Ask TaxVault <span aria-hidden="true">↗</span></button>
        </div>
        <div class="faq-list">
          <details><summary>What files can I upload?</summary><p>You can upload PDF, JPEG, PNG, WebP and TIFF files in Documents.</p></details>
          <details><summary>Can I connect OneDrive?</summary><p>Yes. Connect OneDrive in the document storage settings to import files and folders.</p></details>
          <details><summary>How do I share records with my accountant?</summary><p>Open Accountants to invite your accountant and manage their access, or prepare an accountant pack to share your records.</p></details>
        </div>
      </section>
      <section class="contact-section" id="contact" aria-labelledby="contact-title">
        <div class="contact-copy">
          <h2 id="contact-title">Contact us</h2>
          <p>Have a question about getting started, storing documents or sharing records with your accountant?</p>
          <a href="#faq" class="contact-faq">Browse frequently asked questions <span aria-hidden="true">↑</span></a>
          <img class="section-photo contact-photo" src="assets/branding/taxvault-contact.jpg" width="1672" height="941" loading="lazy" decoding="async" alt="A person writing a message on a laptop beside tax paperwork" />
        </div>
        @if (contactSubmitted()) {
          <div class="contact-success" role="status">
            <strong>Thanks for getting in touch.</strong>
            <span>Your enquiry was sent to TaxVault support.</span>
          </div>
        } @else {
          <form class="contact-form" aria-describedby="contact-form-note" (submit)="submitContact($event)">
            <h3>Write an enquiry</h3>
            <p id="contact-form-note">Include an email address we can reply to. Keep tax file numbers and financial documents out of the message.</p>
            <label for="contact-name">Name</label>
            <input id="contact-name" name="name" type="text" autocomplete="name" maxlength="120" required />
            <label for="contact-email">Email</label>
            <input id="contact-email" name="email" type="email" autocomplete="email" maxlength="254" required />
            <label for="contact-message">How can we help?</label>
            <textarea id="contact-message" name="message" rows="5" maxlength="3000" required></textarea>
            @if (contactError()) { <p class="contact-error" role="alert">{{ contactError() }}</p> }
            <button type="submit" [disabled]="contactPending()">{{ contactPending() ? 'Sending…' : 'Send message' }}</button>
          </form>
        }
      </section>
      <footer>
        <div class="footer-brand"><tv-brand-logo [size]="34" /><p>Keep your tax evidence together.</p></div>
        <nav class="footer-links" aria-label="Footer navigation">
          <div><strong>Explore</strong><a href="#how-it-works">How it works</a><a href="#benefits">Why TaxVault</a><a href="#faq">FAQs</a></div>
          <div><strong>Get started</strong><button type="button" (click)="openLogin()">Log in</button><button type="button" (click)="openTrial()">Get free tax updates</button></div>
          <div><strong>Contact</strong><a href="#contact">Contact us</a><span>Questions about your tax records?</span></div>
        </nav>
        <div class="footer-bottom"><span>© 2026 TaxVault</span><span>TaxVault organises evidence. It does not prepare or lodge tax returns or provide tax advice.</span></div>
      </footer>
      @if (loginOpen) { <div class="backdrop" (click)="closeLogin()"><section class="modal" role="dialog" aria-modal="true" aria-label="Log in to TaxVault" (click)="$event.stopPropagation()"><button class="close" type="button" aria-label="Close login" (click)="closeLogin()">×</button><app-login [embedded]="true" [trialMode]="trialMode" [returnUrl]="loginReturnUrl" /></section></div> }
    </main>
    <app-product-chat #productChat />
  `,
  styles: [`
    :host { display:block; min-height:100vh; }
    .landing-page { min-height:100vh; color:#172b4d; background:#f6f8fb; scroll-behavior:smooth; }
    .site-header { border-top:2px solid #172b4d; border-bottom:1px solid #dfe5ec; background:#fff; }
    .site-nav { display:flex; align-items:center; gap:32px; max-width:1180px; margin:auto; padding:16px 32px; }
    .brand { display:flex; margin-right:8px; color:inherit; text-decoration:none; }
    .nav-links { display:flex; align-items:center; gap:34px; margin-right:auto; }
    .nav-links a,.login-link { color:#1d2735; font:inherit; font-size:14px; font-weight:600; text-decoration:none; }
    .nav-links a:hover,.login-link:hover { color:#1269d3; }
    .nav-actions { display:flex; align-items:center; gap:28px; }
    .nav-actions button { border:0; font:inherit; cursor:pointer; }
    .updates { min-height:44px; padding:0 24px; border-radius:8px; color:#fff; background:#087369; font-size:14px; font-weight:700; }
    .login-link { padding:8px 0; background:transparent; }
    .hero { display:grid; grid-template-columns:minmax(0,.92fr) minmax(440px,1.08fr); align-items:center; gap:72px; max-width:1180px; margin:auto; padding:76px 32px 112px; }
    .copy { max-width:540px; }
    .eyebrow { margin:0 0 16px; color:#1f7ac4; font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
    h1 { margin:0; color:#12345d; font-size:clamp(42px,5vw,68px); font-weight:500; letter-spacing:-.055em; line-height:1.02; }
    .copy > p:not(.eyebrow) { margin:24px 0 0; color:#58708f; font-size:17px; line-height:1.65; }
    .primary { display:inline-flex; align-items:center; min-height:46px; margin-top:32px; padding:0 20px; border:0; border-radius:8px; color:white; background:#123f78; font:inherit; font-size:14px; font-weight:700; cursor:pointer; }
    .preview { padding:20px; border:1px solid #d9e2ed; border-radius:18px; background:white; box-shadow:0 24px 60px rgb(31 63 101 / 12%); }
    .benefits,.pricing-section,.fit-section,.flow-section { max-width:1180px; margin:auto; padding:0 32px 100px; }
    .benefits h2,.trust-strip h2,.fit-heading h2,.flow-heading h2,.pricing-heading h2 { margin:0; color:#18385f; font-size:34px; font-weight:500; }
    .benefit-grid,.fit-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-top:32px; }
    article { min-height:150px; padding:24px; border:1px solid #dce5ee; border-radius:12px; background:white; }
    article h3 { margin:28px 0 10px; color:#234466; font-size:17px; }
    article p { margin:0; color:#6d8197; font-size:13px; line-height:1.6; }
    .workspace-section { max-width:1180px; margin:auto; padding:0 32px 100px; }
    .workspace-heading { max-width:620px; }
    .workspace-heading h2 { margin:0; color:#18385f; font-size:34px; font-weight:500; }
    .workspace-heading p:not(.eyebrow) { color:#6d8197; font-size:15px; line-height:1.7; }
    .workspace-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-top:32px; }
    .workspace-grid article { min-height:150px; }
    .workspace-tag { color:#1f7ac4; font-size:11px; font-weight:700; letter-spacing:.08em; }
    .trust-strip { display:flex; align-items:end; justify-content:space-between; gap:48px; max-width:1180px; margin:0 auto; padding:28px 32px 90px; border-top:1px solid #dce5ee; }
    .trust-strip > p { max-width:480px; margin:0; color:#6d8197; font-size:15px; line-height:1.7; }
    .faq-section { display:grid; grid-template-columns:minmax(220px,.75fr) minmax(0,1.25fr); gap:40px; max-width:1180px; margin:0 auto; padding:36px 32px 64px; border-top:1px solid #dce5ee; }
    .faq-section h2 { margin:0; color:#18385f; font-size:34px; font-weight:500; }
    .faq-list { border-top:1px solid #dce5ee; }
    .faq-list details { border-bottom:1px solid #dce5ee; }
    .faq-list summary { padding:16px 4px; color:#18385f; cursor:pointer; font-weight:700; }
    .faq-list p { margin:0; padding:0 4px 16px; color:#6d8197; font-size:14px; line-height:1.6; }
    .contact-section { display:grid; grid-template-columns:minmax(0,1fr) minmax(320px,460px); gap:64px; max-width:1180px; margin:0 auto; padding:74px 32px 86px; border-top:1px solid #dce5ee; }
    .contact-copy h2 { margin:0; color:#18385f; font-size:42px; font-weight:500; }
    .contact-copy > p:not(.eyebrow) { max-width:480px; margin:20px 0 0; color:#6d8197; font-size:15px; line-height:1.7; }
    .contact-form { display:grid; gap:8px; padding:26px; border:1px solid #dce5ee; border-radius:14px; background:#fff; box-shadow:0 14px 34px rgb(31 63 101 / 8%); }
    .contact-form input,.contact-form textarea { box-sizing:border-box; width:100%; padding:11px 12px; border:1px solid #cbd8e5; border-radius:7px; font:inherit; }
    .footer-brand { display:grid; gap:12px; }
    .footer-brand p { margin:0; color:#6d8197; font-size:13px; }
    .footer-links { display:grid; grid-template-columns:repeat(3,1fr); gap:28px; }
    .footer-links div { display:grid; align-content:start; gap:9px; }
    .footer-links a,.footer-links button,.footer-links span { width:max-content; padding:0; border:0; color:#6d8197; background:transparent; font:inherit; font-size:12px; text-align:left; text-decoration:none; }
    .footer-bottom { display:flex; grid-column:1 / -1; justify-content:space-between; gap:24px; padding-top:18px; border-top:1px solid #dce5ee; color:#8a99aa; font-size:11px; line-height:1.5; }
    footer { display:flex; align-items:center; justify-content:space-between; max-width:1180px; margin:auto; padding:26px 32px 34px; border-top:1px solid #dce5ee; color:#7b8da1; font-size:12px; }
    .backdrop { position:fixed; inset:0; z-index:20; display:grid; place-items:center; padding:24px; background:rgb(15 35 58 / 42%); overflow-y:auto; }
    .modal { position:relative; width:min(100%,520px); max-height:calc(100vh - 48px); overflow-y:auto; border-radius:18px; background:#f5f7fb; box-shadow:0 24px 80px rgb(15 35 58 / 28%); }
    .close { position:absolute; z-index:2; top:12px; right:14px; width:32px; height:32px; border:0; border-radius:50%; color:#52677f; background:transparent; font-size:24px; cursor:pointer; }
    .pricing-section { padding-top:72px; }
    @media (max-width:850px) { .site-nav { gap:20px; } .nav-links { gap:18px; } .hero { grid-template-columns:1fr; gap:48px; padding-top:48px; } .fit-grid,.workspace-grid { grid-template-columns:1fr; } .trust-strip,.contact-section { align-items:start; flex-direction:column; } .contact-section { display:flex; } .faq-section { grid-template-columns:1fr; } }
    @media (max-width:620px) { .site-nav { flex-wrap:wrap; padding:14px 20px; } .nav-links { order:3; width:100%; justify-content:space-between; } .hero,.benefits,.pricing-section,.fit-section,.flow-section,.workspace-section,.trust-strip,.faq-section,.contact-section,footer { padding-left:20px; padding-right:20px; } .benefit-grid,.pricing-grid { grid-template-columns:1fr; } footer { align-items:start; flex-direction:column; gap:16px; } .footer-links { grid-template-columns:repeat(2,1fr); } .footer-bottom { align-items:flex-start; flex-direction:column; gap:8px; } }
    .pricing-heading { max-width:620px; }
    .pricing-heading h2 { margin:0; color:#18385f; font-size:34px; font-weight:500; }
    .pricing-heading p:not(.eyebrow) { color:#6d8197; font-size:15px; line-height:1.7; }
    .pricing-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px; margin-top:32px; }
    .pricing-card { display:flex; flex-direction:column; min-height:300px; padding:28px; border:1px solid #dce5ee; border-radius:14px; background:#fff; transition:transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease; }
    .pricing-card:hover { transform:translateY(-5px); box-shadow:0 14px 34px rgb(31 63 101 / 10%); }
    .pricing-card.selected { border-color:#2b8f88; outline:3px solid rgb(43 143 136 / 22%); outline-offset:4px; transform:translateY(-9px); box-shadow:0 20px 42px rgb(31 63 101 / 16%); }
    .pricing-card.featured { border-width:1px; }
    .pricing-label { color:#1f7ac4; font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
    .pricing-card h3 { margin:16px 0 10px; color:#234466; font-size:21px; }
    .pricing-card > strong { color:#123f78; font-size:28px; }
    .pricing-card > strong small { color:#6d8197; font-size:14px; font-weight:500; }
    .pricing-card p { margin:18px 0 0; color:#6d8197; font-size:14px; line-height:1.6; }
    .pricing-card .primary { align-self:flex-start; margin-top:auto; }
    .pricing-note { margin:18px 0 0; color:#6d8197; font-size:12px; text-align:center; }
  `, `.modal > app-login { display:block; width:100%; } .process-flow { display:flex; align-items:stretch; gap:12px; margin-top:34px; padding:28px; border:1px solid #d8e5ef; border-radius:16px; background:#eef6fd; } .process-node { display:grid; flex:1; gap:7px; min-width:0; } .process-node > span { display:grid; place-items:center; width:38px; height:38px; border:2px solid #8db7d8; border-radius:50%; color:#123f78; background:#fff; font-size:12px; font-weight:700; } .process-node strong { color:#234466; font-size:14px; } .process-node small { color:#6d8197; font-size:11px; line-height:1.4; } .process-node.complete > span { border-color:#2c9a67; color:#fff; background:#2c9a67; } .process-arrow { align-self:center; flex:none; color:#6e9cc0; font-size:24px; } .accountant-branch { display:flex; align-items:flex-start; gap:12px; max-width:360px; margin:18px 0 0 auto; padding:16px 18px; border:1px dashed #9cc3df; border-radius:12px; background:#fff; } .branch-line { color:#6e9cc0; font-size:24px; line-height:1; } .accountant-branch div { display:grid; gap:5px; } .accountant-branch small { color:#1f7ac4; font-size:11px; font-weight:700; text-transform:uppercase; } .accountant-branch strong { color:#234466; font-size:14px; } .accountant-branch p { margin:0; color:#6d8197; font-size:12px; line-height:1.5; } @media (max-width:850px) { .process-flow { align-items:stretch; flex-direction:column; } .process-arrow { align-self:center; transform:rotate(90deg); } .accountant-branch { margin-left:0; } }`,
  `.site-nav, .hero, .benefits, .fit-section, .flow-section, .trust-strip, .contact-section, footer { max-width:1320px; }`,
  `
    .hero-scene {
      background: linear-gradient(90deg, #071727 12%, #071727d9 44%, #07172726 100%),
        url('/assets/branding/taxvault-vault.jpg') right center / cover no-repeat #071727;
    }
    .hero { gap:64px; min-height:540px; padding-top:72px; padding-bottom:80px; }
    .landing-page { font-family:Roboto, "Helvetica Neue", Arial, sans-serif; }
    .landing-page h1, .landing-page h2 { font-family:Raleway, Roboto, sans-serif; }
    .hero h1 { color:#f5f8fc; font-size:clamp(40px, 4.2vw, 60px); line-height:1.08; font-weight:700; letter-spacing:-.045em; }
    .hero h1 em { color:#bceadd; font-family:Georgia, serif; font-weight:400; letter-spacing:-.04em; }
    .hero .copy > p { max-width:440px; color:#c7d4e1; }
    .hero-actions { display:flex; flex-wrap:wrap; align-items:center; gap:16px 24px; margin-top:32px; }
    .hero-actions .primary { margin:0; color:#12345d; background:#f5f8fc; }
    .hero-actions .primary:hover { background:#daeaf8; }
    .hero-actions a { color:#e0edf7; font-size:14px; font-weight:600; text-underline-offset:4px; }
    .hero-actions a span { margin-inline-start:4px; }
    .hero-actions :focus-visible { outline:2px solid #bfe1ff; outline-offset:4px; }
    .preview { margin:0; padding:24px; transform:none; box-shadow:0 16px 48px #0005; }
    .preview-top { display:flex; flex-wrap:wrap; justify-content:space-between; gap:4px 16px; color:#526983; font-size:12px; }
    .preview h2 { margin:20px 0 16px; }
    .review-example { display:grid; grid-template-columns:minmax(0, 1.08fr) minmax(0, 1fr); gap:12px; }
    .review-example > section { min-width:0; padding:14px; border:1px solid #dce5ee; border-radius:10px; }
    .review-example h3 { margin:0 0 12px; color:#234466; font-size:13px; font-weight:700; }
    .example-source { background:#ebf0f4; }
    .example-fields { background:#f8fafb; }
    .sample-receipt {
      padding:14px 12px;
      background:#fffdf6;
      border:1px solid #e9ddc6;
      box-shadow:0 3px 8px #172b4d12;
      color:#30312d;
      font:12px/1.45 "Cascadia Mono", Consolas, monospace;
    }
    .sample-receipt time { display:block; margin-top:4px; color:#55574f; }
    .review-example dl { margin:14px 0 0; }
    .review-example dd { margin:0; font-variant-numeric:tabular-nums; }
    .sample-receipt dl > div { display:flex; flex-wrap:wrap; justify-content:space-between; gap:2px 8px; padding:5px 0; }
    .receipt-total { border-top:1px dashed #a1947d; font-weight:700; }
    .example-fields dl > div { display:grid; gap:3px; padding:7px 0; border-bottom:1px solid #dce5ee; }
    .example-fields dt { color:#526983; font-size:12px; }
    .example-fields dd { color:#172b4d; font-size:14px; font-weight:700; overflow-wrap:anywhere; }
    .example-status { display:inline-block; margin-top:12px; color:#176752; font-size:12px; font-weight:700; }
    .section-intro { display:grid; grid-template-columns:minmax(0, .85fr) minmax(0, 1.15fr); align-items:center; gap:48px; }
    .section-photo { display:block; width:100%; height:auto; border:1px solid #dce5ee; border-radius:14px; }
    .contact-section { align-items:start; gap:48px; }
    .contact-copy h2 { font-weight:700; }
    .contact-copy > p:not(.eyebrow) { margin-top:12px; color:#526983; }
    .contact-faq { display:inline-block; margin-top:14px; color:#065b54; font-weight:700; text-underline-offset:4px; }
    .contact-faq:hover { text-decoration:underline; }
    .contact-faq:focus-visible { outline:2px solid #087369; outline-offset:3px; }
    .contact-photo { max-width:640px; margin-top:24px; }
    .contact-form, .contact-success { width:100%; }
    .contact-form h3 { margin:0; color:#18385f; font-size:19px; }
    .contact-form > p { margin:0 0 8px; color:#526983; font-size:13px; line-height:1.5; }
    .contact-form button { background:#087369; }
    .contact-form button:hover { background:#065b54; }
    .contact-form button:disabled { opacity:.65; cursor:wait; }
    .contact-form .contact-error { color:#9d2525; }
    .flow-heading > p:last-child { margin:12px 0 0; color:#526983; }
    .process-node small { color:#526983; font-size:13px; }
    .faq-section {
      display:grid;
      grid-template-columns:minmax(220px, .75fr) minmax(0, 1.25fr);
      gap:40px;
      max-width:1320px;
      margin:0 auto;
      padding:36px 32px 64px;
      border-top:1px solid #dce5ee;
    }
    .faq-section h2 { margin:0; color:#18385f; font-size:clamp(26px, 3vw, 36px); font-weight:700; }
    .faq-chat {
      min-height:44px;
      margin-top:16px;
      padding:0 16px;
      border:1px solid #087369;
      border-radius:8px;
      background:#fff;
      color:#065b54;
      font-weight:700;
    }
    .faq-chat:hover { background:#e7f1ef; }
    .faq-chat span { margin-inline-start:6px; }
    .faq-list { border-top:1px solid #dce5ee; }
    .faq-list details { border-bottom:1px solid #dce5ee; }
    .faq-list summary { padding:16px 4px; color:#18385f; cursor:pointer; font-weight:700; }
    .faq-list summary:hover { color:#065b54; }
    .faq-list p { margin:0; padding:0 4px 16px; color:#526983; font-size:14px; line-height:1.6; }
    .faq-section :focus-visible { outline:2px solid #087369; outline-offset:3px; }
    .benefits { padding-top:72px; }
    @media (max-width:850px) {
      .hero-scene {
        background-image: linear-gradient(90deg, #071727f2, #07172780),
          url('/assets/branding/taxvault-vault.jpg');
      }
      .hero { min-height:0; gap:32px; padding-top:56px; padding-bottom:56px; }
      .hero-break { display:none; }
      .benefits { padding-top:56px; }
    }
    @media (max-width:480px) {
      .preview { padding:14px; }
      .review-example { grid-template-columns:minmax(0, 1fr); }
    }
    @media (max-width:620px) {
      .section-intro { grid-template-columns:minmax(0, 1fr); gap:20px; }
      .landing-page footer { grid-template-columns:minmax(0, 1fr); }
      .faq-section { grid-template-columns:minmax(0, 1fr); gap:20px; padding:28px 20px 48px; }
      .contact-section { gap:24px; padding-top:40px; }
    }
    @media (prefers-reduced-motion: no-preference) {
      .process-node.is-animated,
      .accountant-branch.is-animated {
        animation: workflow-arrival 560ms ease-out var(--workflow-delay, 0ms);
      }
      .process-node.is-animated > span {
        animation: workflow-marker 560ms ease-out var(--workflow-delay, 0ms);
      }
      @media (min-width:851px) {
        .process-node:nth-child(3) { --workflow-delay:260ms; }
        .process-node:nth-child(5) { --workflow-delay:520ms; }
        .process-node:nth-child(7) { --workflow-delay:780ms; }
        .accountant-branch { --workflow-delay:1040ms; }
      }
      @keyframes workflow-arrival {
        0%, 100% { transform:translateY(0); }
        36% { transform:translateY(-6px); }
      }
      @keyframes workflow-marker {
        0% { scale:1; box-shadow:0 0 0 0 #1f7ac455; }
        40% { scale:1.13; }
        70% { box-shadow:0 0 0 9px #1f7ac400; }
        100% { scale:1; box-shadow:none; }
      }
    }
    @media (hover: hover) and (prefers-reduced-motion: no-preference) {
      :is(.process-node, .accountant-branch, .preview, .benefit-grid article, .fit-grid article, .workspace-grid article) {
        transition:translate 180ms ease-out;
      }
      .process-node > span {
        transition:transform 180ms ease-out;
      }
      :is(.process-node, .accountant-branch, .preview, .benefit-grid article, .fit-grid article, .workspace-grid article):hover {
        translate:0 -5px;
      }
      .process-node:hover > span {
        transform:scale(1.1);
      }
    }
  `,
  `footer { display:grid; grid-template-columns:minmax(220px,.8fr) minmax(0,1.6fr); gap:36px 72px; align-items:start; } .footer-brand { display:grid; gap:12px; } .footer-brand p { margin:0; color:#6d8197; font-size:13px; } .footer-links { display:grid; grid-template-columns:repeat(3,1fr); gap:28px; } .footer-links div { display:grid; align-content:start; gap:9px; } .footer-links strong { color:#234466; font-size:12px; } .footer-links a, .footer-links button, .footer-links span { width:max-content; padding:0; border:0; color:#6d8197; background:transparent; font:inherit; font-size:12px; text-align:left; text-decoration:none; } .footer-links a:hover, .footer-links button:hover { color:#1269d3; cursor:pointer; } .footer-links span { max-width:160px; line-height:1.5; } .footer-bottom { display:flex; grid-column:1 / -1; justify-content:space-between; gap:24px; padding-top:18px; border-top:1px solid #dce5ee; color:#8a99aa; font-size:11px; line-height:1.5; } @media (max-width:850px) { footer { display:grid; } } @media (max-width:620px) { .footer-links { grid-template-columns:repeat(2,1fr); } .footer-bottom { align-items:flex-start; flex-direction:column; gap:8px; } }`],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LandingComponent {
  readonly receipt = { supplier: 'Harbour Stationery', date: '1 Sep 2026', amount: '$86.40' };
  loginOpen = false;
  trialMode = false;
  loginReturnUrl = '/dashboard';
  selectedPricingPlan: 'monthly' | 'trial' | 'yearly' = 'trial';
  readonly contactSubmitted = signal(false);
  readonly contactPending = signal(false);
  readonly contactError = signal('');
  private readonly http = inject(HttpClient);
  private readonly destroyRef = inject(DestroyRef);

  constructor() {
    const host = inject<ElementRef<HTMLElement>>(ElementRef).nativeElement;

    afterNextRender(() => {
      if (typeof IntersectionObserver === 'undefined') return;

      const observer = new IntersectionObserver(entries => {
        for (const entry of entries) {
          if (!entry.isIntersecting) continue;

          entry.target.classList.add('is-animated');
          observer.unobserve(entry.target);
        }
      }, { threshold: 0.5 });

      for (const step of host.querySelectorAll('.process-node, .accountant-branch')) {
        observer.observe(step);
      }

      this.destroyRef.onDestroy(() => observer.disconnect());
    });
  }

  openLogin(): void { this.trialMode = false; this.loginReturnUrl = '/dashboard'; this.loginOpen = true; }
  openTrial(): void { this.trialMode = true; this.loginReturnUrl = '/settings?billing=checkout&plan=trial'; this.loginOpen = true; }
  openPricingSignup(plan: 'monthly' | 'trial' | 'yearly'): void {
    this.selectedPricingPlan = plan;
    this.trialMode = true;
    this.loginReturnUrl = `/settings?billing=checkout&plan=${plan}`;
    this.loginOpen = true;
  }
  closeLogin(): void { this.loginOpen = false; }

  submitContact(event: SubmitEvent): void {
    event.preventDefault();
    if (this.contactPending() || !(event.currentTarget instanceof HTMLFormElement)) return;

    const fields = new FormData(event.currentTarget);
    const name = String(fields.get('name') ?? '').trim();
    const email = String(fields.get('email') ?? '').trim();
    const message = String(fields.get('message') ?? '').trim();
    if (!name || !email || !message) {
      this.contactError.set('Enter your name, email address and message.');
      return;
    }

    this.contactPending.set(true);
    this.contactError.set('');
    this.http.post<void>('/api/contact', { name, email, message }).pipe(
      takeUntilDestroyed(this.destroyRef),
      finalize(() => this.contactPending.set(false)),
    ).subscribe({
      next: () => this.contactSubmitted.set(true),
      error: (error: HttpErrorResponse) => {
        this.contactError.set(error.status === 429
          ? 'Too many messages were submitted. Please wait a few minutes and try again.'
          : 'We couldn’t send your enquiry. Please try again or use Ask TaxVault for product questions.');
      },
    });
  }
}
