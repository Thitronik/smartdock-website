// Eigene lesbare Rechtsseiten für die Informations- und Anfragewebsite.
// Inhalte fachlich mit Händlerbund freigeben; keine Shopbedingungen einblenden.
export function legalPage(React,jsx,Head,language,kind) {
 const lang=['de','en','fr','nl','es'].includes(language)?language:'de';
 const [copy,setCopy]=React.useState(null),[failed,setFailed]=React.useState(false);
 React.useEffect(()=>{let active=true;fetch(new URL('../data/legal-pages.json',import.meta.url)).then(response=>{if(!response.ok)throw Error('legal content');return response.json();}).then(data=>{const entry=data.items.find(item=>item.language===lang);if(!entry)throw Error('language');if(active)setCopy(entry);}).catch(()=>{if(active)setFailed(true);});return()=>{active=false;};},[lang]);
 const errors={de:'Die Angaben konnten nicht geladen werden. Bitte lade die Seite erneut.',en:'The information could not be loaded. Please reload the page.',fr:'Les informations n’ont pas pu être chargées. Veuillez recharger la page.',nl:'De gegevens konden niet worden geladen. Laad de pagina opnieuw.',es:'No se ha podido cargar la información. Vuelve a cargar la página.'};
 if(!copy)return jsx.jsx('main',{className:'min-h-screen pt-28 pb-20',children:jsx.jsx('p',{role:'status',className:'max-w-4xl mx-auto px-6',children:failed?errors[lang]:'…'})});
 const sections=kind==='imprint'?copy.imprintSections:copy.privacySections,title=copy[kind];
 return jsx.jsxs('main',{className:'min-h-screen bg-background pt-28 pb-20',children:[
  jsx.jsxs(Head,{children:[jsx.jsx('html',{lang}),jsx.jsx('title',{children:title+' – SMARTDOCK'}),jsx.jsx('meta',{name:'description',content:title+' · SMARTDOCK · Thitronik GmbH'})]}),
  jsx.jsxs('article',{className:'max-w-4xl mx-auto px-4 sm:px-6 lg:px-8',children:[
   jsx.jsxs('nav',{'aria-label':lang==='fr'?'Fil d’Ariane':'Breadcrumb',className:'mb-8 text-sm text-muted-foreground',children:[jsx.jsx('a',{href:window.__sdPagesUrl?.(lang==='de'?'/':'/'+lang+'/')||(lang==='de'?'/':'/'+lang+'/'),className:'hover:text-primary',children:copy.home}),' › ',title]}),
   jsx.jsx('h1',{className:'mb-6',children:title}),
   jsx.jsx('p',{className:'text-muted-foreground mb-12',children:copy.date}),
   ...sections.map(([heading,...paragraphs],index)=>jsx.jsxs('section',{className:'mb-10',children:[jsx.jsx('h2',{className:'text-2xl mb-4',children:heading}),...paragraphs.map((paragraph,i)=>jsx.jsx('p',{className:'text-muted-foreground leading-relaxed mb-4',children:paragraph},i))]},index))
  ]})
 ]});
}
