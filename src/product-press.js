const articlePdf='/media/press/boote-smartdock-11-2026.pdf';
const articlePreview='/media/press/boote-smartdock-preview.jpg';
const viewerCopy={
 DE:{loading:'PDF wird geladen …',error:'Die PDF konnte nicht angezeigt werden. Öffne sie bitte über den Link oben in einem neuen Tab.',page:'Seite',zoomIn:'Vergrößern',zoomOut:'Verkleinern',fit:'An Breite anpassen'},
 EN:{loading:'Loading PDF …',error:'The PDF could not be displayed. Use the link above to open it in a new tab.',page:'Page',zoomIn:'Zoom in',zoomOut:'Zoom out',fit:'Fit to width'},
 FR:{loading:'Chargement du PDF…',error:'Le PDF n’a pas pu être affiché. Veuillez utiliser le lien ci-dessus pour l’ouvrir dans un nouvel onglet.',page:'Page',zoomIn:'Agrandir',zoomOut:'Réduire',fit:'Adapter à la largeur'},
 NL:{loading:'PDF laden …',error:'De PDF kon niet worden weergegeven. Open deze via de link hierboven in een nieuw tabblad.',page:'Pagina',zoomIn:'Vergroten',zoomOut:'Verkleinen',fit:'Aan breedte aanpassen'},
 ES:{loading:'Cargando PDF…',error:'No se ha podido mostrar el PDF. Usa el enlace de arriba para abrirlo en otra pestaña.',page:'Página',zoomIn:'Ampliar',zoomOut:'Reducir',fit:'Ajustar al ancho'}
};
const copies={
 DE:{title:'BOOTE hat SMARTDOCK getestet',description:'Im Artikel „Anlegen ohne Steuerstand“ berichtet BOOTE über SMARTDOCK und die Bedienung per Smartphone. Lies den vollständigen Praxistest aus Ausgabe 11/2026.',open:'Artikel ansehen (PDF)',preview:'BOOTE-Artikel vergrößern',dialog:'BOOTE · Anlegen ohne Steuerstand · 11/2026',close:'Schließen',newTab:'In neuem Tab öffnen',download:'PDF herunterladen',language:''},
 EN:{title:'BOOTE puts SMARTDOCK to the test',description:'In “Anlegen ohne Steuerstand”, BOOTE reports on SMARTDOCK and smartphone control. Read the full review from issue 11/2026.',open:'View article (PDF)',preview:'Enlarge the BOOTE article',dialog:'BOOTE · Anlegen ohne Steuerstand · 11/2026',close:'Close',newTab:'Open in a new tab',download:'Download PDF',language:'Article in German.'},
 FR:{title:'SMARTDOCK à l’essai dans BOOTE',description:'Dans « Anlegen ohne Steuerstand », BOOTE présente SMARTDOCK et la commande par smartphone. Consultez l’article complet du numéro 11/2026.',open:'Consulter l’article (PDF)',preview:'Agrandir l’article BOOTE',dialog:'BOOTE · Anlegen ohne Steuerstand · 11/2026',close:'Fermer',newTab:'Ouvrir dans un nouvel onglet',download:'Télécharger le PDF',language:'Article en allemand.'},
 NL:{title:'BOOTE test SMARTDOCK',description:'In het artikel ‘Anlegen ohne Steuerstand’ bespreekt BOOTE SMARTDOCK en de bediening via smartphone. Lees de volledige praktijktest uit nummer 11/2026.',open:'Artikel bekijken (PDF)',preview:'BOOTE-artikel vergroten',dialog:'BOOTE · Anlegen ohne Steuerstand · 11/2026',close:'Sluiten',newTab:'Openen in nieuw tabblad',download:'PDF downloaden',language:'Artikel in het Duits.'},
 ES:{title:'BOOTE pone SMARTDOCK a prueba',description:'En «Anlegen ohne Steuerstand», BOOTE presenta SMARTDOCK y el control desde el smartphone. Lee la prueba completa del número 11/2026.',open:'Ver artículo (PDF)',preview:'Ampliar el artículo de BOOTE',dialog:'BOOTE · Anlegen ohne Steuerstand · 11/2026',close:'Cerrar',newTab:'Abrir en otra pestaña',download:'Descargar PDF',language:'Artículo en alemán.'}
};
const url=path=>window.__sdPagesUrl?.(path)||path;

// Keep the compiled application's integration small; presentation and viewer live here.
export function createBooteArticle(React,jsx,Motion){
 const PdfReader=createPdfReader(React,jsx);
 return function BooteArticle({language='DE'}){
  const locale=language.toUpperCase().split('-')[0],copy=copies[locale]||copies.DE;
  const [open,setOpen]=React.useState(false),viewer=React.useRef(null);
  const titleId=React.useId();
  React.useEffect(()=>{
   if(document.querySelector('link[data-sd-product-press]'))return;
   const link=document.createElement('link');link.rel='stylesheet';link.href=new URL('./product-press.css?v=20261105press1',import.meta.url).href;link.dataset.sdProductPress='';document.head.append(link);
  },[]);
  React.useEffect(()=>{
   if(!open)return;
   const dialog=viewer.current;if(!dialog)return;
   const previousOverflow=document.body.style.overflow;
   dialog.showModal();dialog.querySelector('button')?.focus();document.body.style.overflow='hidden';
   return()=>{if(dialog.open)dialog.close();document.body.style.overflow=previousOverflow;};
  },[open]);
  const close=()=>setOpen(false);
  const show=()=>setOpen(true);
  return jsx.jsxs(Motion.div,{
   initial:{opacity:0,y:20},whileInView:{opacity:1,y:0},viewport:{once:true,margin:'-100px'},transition:{duration:.6},
   className:'grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-20 items-center',
   'data-press-article':'boote',children:[
    jsx.jsxs('div',{className:'order-1',children:[
     jsx.jsx('h2',{className:'text-3xl md:text-4xl font-semibold mb-8 text-balance text-foreground',children:copy.title}),
     jsx.jsx('p',{className:'text-base text-muted-foreground leading-normal mb-8',children:copy.description}),
     jsx.jsx('button',{type:'button',onClick:show,'aria-haspopup':'dialog',className:'sd-press-open inline-flex items-center gap-2 text-primary hover:text-primary/80 font-medium transition-colors',children:copy.open+' ↗'}),
     copy.language&&jsx.jsx('p',{className:'mt-4 text-sm text-muted-foreground',children:copy.language})
    ]}),
    jsx.jsxs('button',{type:'button',onClick:show,'aria-label':copy.preview,'aria-haspopup':'dialog',className:'sd-press-preview order-2 aspect-[4/3]',children:[
     jsx.jsx('img',{src:url(articlePreview),alt:copy.dialog+' · '+(viewerCopy[locale]||viewerCopy.DE).page+' 88–89',loading:'lazy',width:1600,height:1093}),
     jsx.jsx('span',{className:'sd-press-preview-label',children:copy.open+' ↗'})
    ]}),
    jsx.jsxs('dialog',{ref:viewer,className:'sd-press-viewer','aria-labelledby':titleId,onCancel:event=>{event.preventDefault();close();},onClose:close,onClick:event=>{
     if(event.target!==viewer.current)return;
     const rect=viewer.current.getBoundingClientRect();
     if(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom)close();
    },children:[
     jsx.jsxs('div',{className:'sd-press-toolbar',children:[
      jsx.jsx('h2',{id:titleId,children:copy.dialog}),
      jsx.jsxs('div',{className:'sd-press-actions',children:[
       jsx.jsx('a',{href:url(articlePdf),target:'_blank',rel:'noopener noreferrer',children:copy.newTab}),
       jsx.jsx('a',{href:url(articlePdf),download:'SMARTDOCK-BOOTE-11-2026.pdf',children:copy.download}),
       jsx.jsx('button',{type:'button',onClick:close,autoFocus:true,'aria-label':copy.close,children:copy.close+' ×'})
      ]})
     ]}),
     open&&jsx.jsx(PdfReader,{pdfUrl:url(articlePdf),label:copy.dialog,copy:viewerCopy[locale]||viewerCopy.DE})
    ]})
   ]
  });
 };
}

function createPdfReader(React,jsx){
 return function PdfReader({pdfUrl,label,copy}){
  const scroll=React.useRef(null),pages=React.useRef(null),documentRef=React.useRef(null);
  const [ready,setReady]=React.useState(false),[zoom,setZoom]=React.useState(1),[width,setWidth]=React.useState(0),[status,setStatus]=React.useState(copy.loading);
  React.useEffect(()=>{
   const resize=new ResizeObserver(()=>setWidth(scroll.current?.clientWidth||0));
   if(scroll.current)resize.observe(scroll.current);return()=>resize.disconnect();
  },[]);
  React.useEffect(()=>{
   let active=true,task;
   (async()=>{
    try{
     const pdfjs=await import('./pdfjs/pdf.min.js');if(!active)return;
     pdfjs.GlobalWorkerOptions.workerSrc=new URL('./pdfjs/pdf.worker.min.js',import.meta.url).href;
     task=pdfjs.getDocument({url:pdfUrl,wasmUrl:new URL('./pdfjs/wasm/',import.meta.url).href,stopAtErrors:true});const pdf=await task.promise;
     if(active){documentRef.current=pdf;setReady(true);}
    }catch{if(active)setStatus(copy.error);}
   })();
   return()=>{active=false;documentRef.current=null;if(task)void task.destroy().catch(()=>{});};
  },[pdfUrl,copy]);
  React.useEffect(()=>{
   if(!ready||!documentRef.current||!pages.current)return;
   const pdf=documentRef.current,container=pages.current;
   let active=true;const tasks=[];container.replaceChildren();setStatus(copy.loading);
   (async()=>{
    try{
     for(let number=1;number<=pdf.numPages;number++){
      const page=await pdf.getPage(number);if(!active)return;
      const natural=page.getViewport({scale:1});
      const viewport=page.getViewport({scale:Math.max(200,(width||scroll.current.clientWidth)-32)/natural.width*zoom});
      const pixelRatio=Math.min(window.devicePixelRatio||1,2,Math.sqrt(8000000/(viewport.width*viewport.height)));
      const canvas=document.createElement('canvas');canvas.width=Math.ceil(viewport.width*pixelRatio);canvas.height=Math.ceil(viewport.height*pixelRatio);
      canvas.style.width=Math.floor(viewport.width)+'px';canvas.style.height=Math.floor(viewport.height)+'px';canvas.setAttribute('role','img');canvas.setAttribute('aria-label',copy.page+' '+number+' / '+pdf.numPages);canvas.dataset.pageNumber=String(number);
      container.append(canvas);
      const task=page.render({canvasContext:canvas.getContext('2d'),viewport,transform:pixelRatio===1?null:[pixelRatio,0,0,pixelRatio,0,0]});tasks.push(task);await task.promise;if(!active)return;
      canvas.dataset.rendered='true';
      const caption=document.createElement('p');caption.className='sd-press-page-label';caption.textContent=copy.page+' '+number+' / '+pdf.numPages;container.append(caption);
     }
     if(active)setStatus('');
    }catch(error){if(active&&error?.name!=='RenderingCancelledException')setStatus(copy.error);}
   })();
   return()=>{active=false;tasks.forEach(task=>task.cancel());};
  },[ready,zoom,width,copy]);
  return jsx.jsxs('div',{className:'sd-press-pdf',children:[
   jsx.jsxs('div',{className:'sd-press-zoom',children:[
    jsx.jsx('button',{type:'button','aria-label':copy.zoomOut,disabled:zoom<=.5,onClick:()=>setZoom(value=>Math.max(.5,value-.25)),children:'−'}),
    jsx.jsx('button',{type:'button',title:copy.fit,onClick:()=>setZoom(1),children:Math.round(zoom*100)+'%'}),
    jsx.jsx('button',{type:'button','aria-label':copy.zoomIn,disabled:zoom>=4,onClick:()=>setZoom(value=>Math.min(4,value+.25)),children:'+'})
   ]}),
   jsx.jsxs('div',{className:'sd-press-pages',ref:scroll,tabIndex:0,role:'region','aria-label':label,children:[
    jsx.jsx('p',{role:'status','aria-live':'polite',className:'sd-press-status',children:status}),
    jsx.jsx('div',{ref:pages,className:'sd-press-canvas-list'})
   ]})
  ]});
 };
}
