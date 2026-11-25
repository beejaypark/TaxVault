import { HttpClient } from '@angular/common/http';
import { afterNextRender, ChangeDetectionStrategy, Component, DestroyRef, ElementRef, inject, Injector, signal, viewChild } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';

interface ChatCategory {
  id: string;
  label: string;
  questions: string[];
}

@Component({
  selector: 'app-product-chat',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (open()) {
      <section class="chat-panel" id="product-chat" aria-labelledby="chat-title" (keydown.escape)="close()">
        <header>
          <h2 id="chat-title">Ask TaxVault</h2>
          <button class="close-chat" type="button" aria-label="Close chat" (click)="close()">×</button>
        </header>
        <div class="transcript" #transcript>
          <p class="greeting">Ask about uploads, storage or sharing with your accountant.</p>
          <div role="log" aria-label="Chat messages">
            @for (message of messages(); track $index) {
              <p class="message" [class.visitor]="message.visitor">
                <span class="visually-hidden">{{ message.visitor ? 'You' : 'TaxVault' }}</span>
                {{ message.text }}
                @if (message.contact) {
                  <a href="#contact" (click)="open.set(false)">Contact us</a>
                }
              </p>
            }
          </div>
          @if (!messages().length) {
            <nav class="category-tabs" aria-label="Ask TaxVault topics">
              @for (category of categories(); track category.id) {
                <button type="button" [class.selected]="selectedCategory() === category.id"
                        (click)="selectCategory(category.id)">{{ category.label }}</button>
              }
            </nav>
            <div class="suggestions" aria-label="Suggested questions">
              @for (question of selectedQuestions(); track question) {
                <button type="button" (click)="send(question)">{{ question }}</button>
              }
            </div>
          }
          @if (pending()) { <p class="greeting" role="status">Finding an answer…</p> }
          @if (messages().length && !pending()) {
            <button type="button" class="browse-topics" (click)="browseTopics()">← Browse other topics</button>
          }
        </div>
        <p class="chat-note" id="chat-note">AI may answer product questions. Avoid sharing tax file numbers or personal details.</p>
        <form (submit)="submit($event, questionInput)">
          <input #questionInput name="question" aria-label="Your product question" aria-describedby="chat-note" placeholder="Type your question" maxlength="600" required />
          <button class="send" type="submit" [disabled]="pending()">Send</button>
        </form>
      </section>
    }
    <button #launcher class="launcher" type="button" [attr.aria-expanded]="open()"
      [attr.aria-controls]="open() ? 'product-chat' : null" (click)="toggle()">Ask TaxVault</button>
  `,
  styles: `
    :host { position:fixed; right:24px; bottom:24px; z-index:15; display:grid; justify-items:end; gap:12px; }
    button, input { font:inherit; }
    button { cursor:pointer; }
    button:focus-visible, a:focus-visible, input:focus-visible { outline:2px solid #1f7ac4; outline-offset:2px; }
    .launcher, .send { border:0; border-radius:8px; background:#123f78; color:#fff; padding:10px 16px; font-weight:700; }
    .launcher { min-height:44px; box-shadow:0 4px 16px #07172730; }
    .launcher:hover, .send:hover { background:#0c305d; }
    .send:disabled { opacity:.6; cursor:default; }
    .chat-panel {
      width:min(360px, calc(100vw - 48px)); height:min(480px, calc(100dvh - 100px));
      min-height:min(320px, calc(100dvh - 100px)); max-height:calc(100dvh - 100px); resize:vertical;
      display:grid; grid-template-rows:auto minmax(0, 1fr) auto auto;
      overflow:hidden; border:1px solid #cbd8e5; border-radius:14px; background:#fff;
      color:#172b4d; box-shadow:0 12px 40px #07172735; font-size:14px;
    }
    header { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:12px 16px; border-bottom:1px solid #e3eaf1; }
    h2 { margin:0; font-size:16px; }
    .close-chat { width:32px; height:32px; border:0; border-radius:6px; background:#eef4fa; color:#12345d; font-size:22px; line-height:1; }
    .transcript { min-height:0; overflow:auto; padding:16px; }
    .greeting, .message { margin:0 0 8px; line-height:1.45; }
    .message { width:fit-content; max-width:92%; padding:8px 12px; border-radius:8px; background:#edf3f9; white-space:pre-wrap; overflow-wrap:anywhere; }
    .visitor { margin-inline-start:auto; background:#123f78; color:#fff; }
    .message a { display:block; color:#123f78; width:fit-content; }
    .category-tabs { display:flex; gap:6px; overflow-x:auto; margin:16px 0 10px; padding-bottom:3px; scrollbar-width:thin; }
    .category-tabs button { flex:none; padding:6px 9px; border:1px solid #ccdcea; border-radius:999px; background:#f7fafc; color:#46617e; font-size:12px; }
    .category-tabs button.selected { border-color:#123f78; color:#fff; background:#123f78; }
    .suggestions { display:grid; gap:8px; }
    .suggestions button { padding:6px 10px; border:1px solid #ccdcea; border-radius:7px; background:#fff; color:#123f78; text-align:left; }
    .suggestions button:hover { background:#edf3f9; }
    .browse-topics { margin-top:8px; padding:6px 0; border:0; background:transparent; color:#123f78; font-size:12px; text-decoration:underline; text-underline-offset:2px; }
    .browse-topics:hover { color:#0c305d; }
    .chat-note { margin:0; padding:8px 16px 0; color:#4c6176; font-size:12px; border-top:1px solid #e3eaf1; }
    form { display:flex; gap:8px; padding:12px 16px 16px; }
    input { flex:1; min-width:0; padding:8px; border:1px solid #cbd8e5; border-radius:7px; color:#172b4d; background:#fff; }
    .visually-hidden { position:absolute; width:1px; height:1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; }
    @media (max-width:620px) {
      :host { right:12px; bottom:12px; }
      .chat-panel { width:min(360px, calc(100vw - 24px)); }
      input { font-size:16px; }
    }
  `,
})
export class ProductChatComponent {
  private readonly http = inject(HttpClient);
  private readonly injector = inject(Injector);
  private readonly destroyRef = inject(DestroyRef);
  private readonly transcript = viewChild<ElementRef<HTMLDivElement>>('transcript');
  private readonly input = viewChild<ElementRef<HTMLInputElement>>('questionInput');
  private readonly launcher = viewChild.required<ElementRef<HTMLButtonElement>>('launcher');
  private readonly questions: string[] = [];
  private openTrigger: HTMLElement | null = null;

  readonly categories = signal<ChatCategory[]>([
    { id: 'dashboard', label: 'Dashboard', questions: ['What can I see on the Dashboard?', 'How do I check my tax readiness?'] },
    { id: 'documents', label: 'Documents', questions: ['What files can I upload?', 'How do I import documents from OneDrive?'] },
    { id: 'financial-years', label: 'Financial years', questions: ['How are documents organised by financial year?', 'How do I switch the active financial year?'] },
    { id: 'reviews', label: 'Reviews', questions: ['How do I review extracted details?', 'Can I correct an extracted amount or date?'] },
    { id: 'transactions', label: 'Income & expenses', questions: ['How do I record income and expenses?', 'Can I attach a receipt to a transaction?'] },
    { id: 'properties', label: 'Properties', questions: ['How do I track a property?', 'Can I record property expenses and periods?'] },
    { id: 'investments', label: 'Investments', questions: ['How do I record an investment?', 'Can I keep investment evidence in TaxVault?'] },
    { id: 'tax-news', label: 'Tax news', questions: ['Where can I find the latest tax news?', 'Can TaxVault explain tax news for my situation?'] },
    { id: 'tax-return-pack', label: 'Tax return pack', questions: ['What is a tax return pack?', 'What records are included in a tax return pack?'] },
    { id: 'accountants', label: 'Accountants', questions: ['How do I share with my accountant?', 'How do I manage my accountant’s access?'] },
    { id: 'settings', label: 'Settings', questions: ['Where can I change my TaxVault settings?', 'How do I manage document storage and notifications?'] },
  ]);
  readonly selectedCategory = signal('dashboard');
  readonly open = signal(false);
  readonly pending = signal(false);
  readonly messages = signal<{ text: string; visitor?: boolean; contact?: boolean }[]>([]);

  readonly selectedQuestions = () => this.categories().find(category => category.id === this.selectedCategory())?.questions ?? [];

  toggle(): void {
    if (this.open()) {
      this.close();
      return;
    }

    this.openChat();
  }

  openChat(trigger?: HTMLElement): void {
    this.openTrigger = trigger ?? null;
    if (this.open()) {
      this.input()?.nativeElement.focus();
      return;
    }

    this.open.set(true);
    this.updatePanel(true);
    if (!this.messages().length) {
      this.http.get<{ categories: ChatCategory[] }>('/api/product-chat/suggestions')
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe({
          next: ({ categories }) => {
            if (!categories?.length) return;
            this.categories.set(categories);
            this.selectedCategory.set(categories[0].id);
          },
          error: () => { /* Keep the starter questions available. */ },
        });
    }
  }

  selectCategory(categoryId: string): void {
    this.selectedCategory.set(categoryId);
  }

  browseTopics(): void {
    this.questions.length = 0;
    this.messages.set([]);
    this.pending.set(false);
    this.updatePanel(true);
  }

  close(): void {
    this.open.set(false);
    (this.openTrigger ?? this.launcher().nativeElement).focus();
    this.openTrigger = null;
  }

  submit(event: SubmitEvent, input: HTMLInputElement): void {
    event.preventDefault();
    if (this.send(input.value)) input.value = '';
  }

  send(value: string): boolean {
    const question = value.trim();
    if (!question || this.pending()) return false;

    if (/(?<!\d)(?:\d[ -]?){7,8}\d(?![ -]?\d)/.test(question)) {
      this.reply('Please remove any tax file numbers or other personal details and ask again.');
      return true;
    }

    this.questions.push(question);
    this.messages.update(messages => [...messages, { text: question, visitor: true }]);
    this.pending.set(true);
    this.updatePanel(true);
    this.http.post<{ answer: string | null }>('/api/product-chat', { questions: this.questions.slice(-5) })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: ({ answer }) => this.reply(answer),
        error: () => this.reply(null),
      });
    return true;
  }

  private reply(answer: string | null): void {
    this.pending.set(false);
    this.messages.update(messages => [...messages, {
      text: answer ?? 'I couldn’t answer that here. You can reach the team below.',
      contact: !answer,
    }]);
    this.updatePanel();
  }

  private updatePanel(focusInput = false): void {
    afterNextRender(() => {
      const transcript = this.transcript()?.nativeElement;
      if (transcript) transcript.scrollTop = transcript.scrollHeight;
      if (focusInput) this.input()?.nativeElement.focus();
    }, { injector: this.injector });
  }
}
