import { suggestAtoCategory } from './ato-categories';

describe('suggestAtoCategory', () => {
  it('does not treat an electricity bill as a repair', () => {
    expect(suggestAtoCategory('AGL ELECTRICITY', 'expense')).not.toBe('rental_repairs');
  });

  it('suggests repairs for tradespeople', () => {
    expect(suggestAtoCategory('Sparky Electrician Pty Ltd', 'expense')).toBe('rental_repairs');
  });
});
