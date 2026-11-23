import { Component, Input } from '@angular/core';
import { FontAwesomeModule } from '@fortawesome/angular-fontawesome';
import {
  faArrowLeft,
  faArrowRight,
  faArrowTrendUp,
  faCalendarDays,
  faChartLine,
  faCircleCheck,
  faCircleExclamation,
  faCircleQuestion,
  faClipboardCheck,
  faCloudArrowUp,
  faDollarSign,
  faDownload,
  faFile,
  faFileInvoiceDollar,
  faFileLines,
  faFolder,
  faGauge,
  faGear,
  faHouse,
  faMagnifyingGlass,
  faNewspaper,
  faBell,
  faPenToSquare,
  faPlus,
  faPaperPlane,
  faUserTie,
  faReceipt,
  faRightLeft,
  faTags,
  faTrashCan,
  faWandMagicSparkles,
  faXmark,
  type IconDefinition,
} from '@fortawesome/free-solid-svg-icons';

export type TaxIconName =
  | 'arrowLeft'
  | 'arrowRight'
  | 'investment'
  | 'financialYear'
  | 'chart'
  | 'check'
  | 'warning'
  | 'review'
  | 'upload'
  | 'currency'
  | 'download'
  | 'file'
  | 'folder'
  | 'taxReturn'
  | 'document'
  | 'dashboard'
  | 'property'
  | 'edit'
  | 'plus'
  | 'receipt'
  | 'transactions'
  | 'classification'
  | 'extraction'
  | 'delete'
  | 'close'
  | 'accountant'
  | 'send'
  | 'search'
  | 'bell'
  | 'news'
  | 'settings'
  | 'help';

const ICONS: Record<TaxIconName, IconDefinition> = {
  arrowLeft: faArrowLeft,
  arrowRight: faArrowRight,
  investment: faArrowTrendUp,
  financialYear: faCalendarDays,
  chart: faChartLine,
  check: faCircleCheck,
  warning: faCircleExclamation,
  review: faClipboardCheck,
  upload: faCloudArrowUp,
  currency: faDollarSign,
  download: faDownload,
  file: faFile,
  folder: faFolder,
  taxReturn: faFileInvoiceDollar,
  document: faFileLines,
  dashboard: faGauge,
  property: faHouse,
  edit: faPenToSquare,
  plus: faPlus,
  receipt: faReceipt,
  transactions: faRightLeft,
  classification: faTags,
  extraction: faWandMagicSparkles,
  delete: faTrashCan,
  close: faXmark,
  accountant: faUserTie,
  send: faPaperPlane,
  search: faMagnifyingGlass,
  bell: faBell,
  news: faNewspaper,
  settings: faGear,
  help: faCircleQuestion,
};

@Component({
  selector: 'tv-icon',
  standalone: true,
  imports: [FontAwesomeModule],
  template: '<fa-icon [icon]="iconDefinition" [fixedWidth]="true" aria-hidden="true" />',
  styles: [':host { display: inline-flex; align-items: center; justify-content: center; }'],
})
export class TaxIconComponent {
  @Input({ required: true }) name!: TaxIconName;

  get iconDefinition(): IconDefinition {
    return ICONS[this.name];
  }
}
