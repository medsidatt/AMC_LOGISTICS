import{r as i,j as t}from"./app-cT5s_WWZ.js";import{c as n}from"./shield-alert-spvL-cYy.js";import{X as l}from"./AuthenticatedLayout-DLo4n7UW.js";/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const m=[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"M12 16v-4",key:"1dtifu"}],["path",{d:"M12 8h.01",key:"e9boi3"}]],x=n("info",m);function y({id:r,children:o}){const e=`hint_dismissed_${r}`,[s,a]=i.useState(()=>{try{return localStorage.getItem(e)!=="1"}catch{return!0}});if(!s)return null;const c=()=>{try{localStorage.setItem(e,"1")}catch{}a(!1)};return t.jsxs("div",{className:"flex items-start gap-2 rounded-xl border border-[var(--color-primary)]/30 bg-[var(--color-primary)]/5 p-3 text-sm text-[var(--color-text-secondary)] mb-4",children:[t.jsx(x,{size:16,className:"text-[var(--color-primary)] mt-0.5 shrink-0"}),t.jsx("div",{className:"flex-1",children:o}),t.jsx("button",{type:"button",onClick:c,"aria-label":"Fermer",className:"text-[var(--color-text-muted)] hover:text-[var(--color-text)] shrink-0",children:t.jsx(l,{size:16})})]})}export{y as H};
