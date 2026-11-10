// Eigene lesbare Rechtsseiten für die Informations- und Anfragewebsite.
// Händlerbund-Fassungen vom 10.11.2026; freigegebene Anschrift 9–15.
export function legalPage(React,jsx,Head,language,kind) {
 const lang=['de','en','fr','nl','es'].includes(language)?language:'de';
 const [copy,setCopy]=React.useState(null),[failed,setFailed]=React.useState(false);
 React.useEffect(()=>{let active=true;fetch('/data/legal-pages.json?v=20261110legal1').then(response=>{if(!response.ok)throw Error('legal content');return response.json();}).then(data=>{const entry=data.items.find(item=>item.language===lang);if(!entry)throw Error('language');if(active)setCopy(entry);}).catch(()=>{if(active)setFailed(true);});return()=>{active=false;};},[lang]);
 const errors={de:'Die Angaben konnten nicht geladen werden. Bitte lade die Seite erneut.',en:'The information could not be loaded. Please reload the page.',fr:'Les informations n’ont pas pu être chargées. Veuillez recharger la page.',nl:'De gegevens konden niet worden geladen. Laad de pagina opnieuw.',es:'No se ha podido cargar la información. Vuelve a cargar la página.'};
 if(!copy)return jsx.jsx('main',{className:'min-h-screen pt-28 pb-20',children:jsx.jsx('p',{role:'status',className:'max-w-4xl mx-auto px-6',children:failed?errors[lang]:'…'})});
 const blocks=kind==='imprint'?copy.imprintBlocks:copy.privacyBlocks,title=copy[kind];
 return jsx.jsxs('main',{className:'min-h-screen bg-background pt-28 pb-20',children:[
  jsx.jsxs(Head,{children:[jsx.jsx('html',{lang}),jsx.jsx('title',{children:title+' – SMARTDOCK'}),jsx.jsx('meta',{name:'description',content:title+' · SMARTDOCK · Thitronik GmbH'})]}),
  jsx.jsxs('article',{className:'max-w-4xl mx-auto px-4 sm:px-6 lg:px-8',style:{overflowWrap:'anywhere'},children:[
   jsx.jsxs('nav',{'aria-label':lang==='fr'?'Fil d’Ariane':'Breadcrumb',className:'mb-8 text-sm text-muted-foreground',children:[jsx.jsx('a',{href:window.__sdPagesUrl?.(lang==='de'?'/':'/'+lang+'/')||(lang==='de'?'/':'/'+lang+'/'),className:'hover:text-primary',children:copy.home}),' › ',title]}),
   jsx.jsx('h1',{className:'mb-6',children:title}),
   jsx.jsx('p',{className:'text-muted-foreground mb-12',children:copy.date}),
   ...blocks.map((block,index)=>jsx.jsx(block.type==='p'?'p':'h2',{className:block.type==='p'?'text-muted-foreground leading-relaxed mb-6':block.type==='h2'?'text-2xl mt-12 mb-5':'text-xl mt-8 mb-4',style:{whiteSpace:'pre-line'},children:block.runs.map((run,i)=>{let node=legalText(jsx,run.text);if(run.italic)node=jsx.jsx('em',{children:node});if(run.bold)node=jsx.jsx('strong',{children:node});return jsx.jsx(React.Fragment,{children:node},i);})},index))
  ]})
 ]});
}

// Render source text as React nodes; never interpret imported legal text as HTML.
export function legalText(jsx,text){
 const pattern=/(https?:\/\/[^\s]+|[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})/gi;
 const nodes=[];let cursor=0,match;
 while((match=pattern.exec(text))){
  if(match.index>cursor)nodes.push(text.slice(cursor,match.index));
  const label=match[0].replace(/[.,;:!?)]+$/,'');
  const email=!label.startsWith('http');
  nodes.push(jsx.jsx('a',{href:email?'mailto:'+label:label,className:'text-primary underline underline-offset-4',...(email?{}:{target:'_blank',rel:'noopener noreferrer'}),children:label},match.index));
  if(label.length<match[0].length)nodes.push(match[0].slice(label.length));
  cursor=pattern.lastIndex;
 }
 if(cursor<text.length)nodes.push(text.slice(cursor));
 return nodes;
}
