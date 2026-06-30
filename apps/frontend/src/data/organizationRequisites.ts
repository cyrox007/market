/** Юридические реквизиты продавца (в т.ч. для оферты и эквайринга). */
export const organizationRequisites = {
  fullName: 'ООО «МЕБЕЛЬНЫЙ РАЙ»',
  inn: '4823025960',
  kpp: '482301001',
  ogrn: '1054800242639',
  okpo: '74018808',
  legalAddress: '398007 г. Липецк, Ул.Римского-Корсакова, д.1а',
  phoneDisplay: '+7 (4742) 48-00-00',
  phoneHref: 'tel:+74742480000',
  director: 'Шипулин Анатолий Николаевич',
  bank: {
    name: 'АО «РАЙФФАЙЗЕНБАНК»',
    bik: '044525700',
    checkingAccount: '40702810400001453226',
    correspondentAccount: '30101810200000000700',
  },
} as const;
