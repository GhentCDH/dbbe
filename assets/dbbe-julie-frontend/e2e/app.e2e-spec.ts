import { DbbeJuliePage } from './app.po';

describe('dbbe-julie App', () => {
  let page: DbbeJuliePage;

  beforeEach(() => {
    page = new DbbeJuliePage();
  });

  it('should display message saying app works', () => {
    page.navigateTo();
    expect(page.getParagraphText()).toEqual('app works!');
  });
});
