export function dealerLink(language = 'DE') {
 const lang = String(language).toLowerCase();
 const labels = {de:'Händlerbereich',en:'Dealer area',fr:'Espace revendeurs',nl:'Dealerportaal',es:'Área de distribuidores'};
 return {href:(lang==='de'?'':'/'+lang)+'/haendlerbereich/',children:labels[lang]||labels.de,className:'text-base font-medium text-foreground hover:text-primary transition-colors py-2'};
}
