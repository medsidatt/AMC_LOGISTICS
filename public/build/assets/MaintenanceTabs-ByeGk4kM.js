import{b as c,j as a}from"./app-emUcqYEg.js";import{c as s}from"./clsx-B-dksMZM.js";import{c as r,T as o}from"./Card-CZYRHS0M.js";/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const i=[["path",{d:"M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8",key:"1357e3"}],["path",{d:"M3 3v5h5",key:"1xhq8a"}],["path",{d:"M12 7v5l4 2",key:"1fdv2h"}]],h=r("history",i);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const l=[["path",{d:"M15 12h-5",key:"r7krc0"}],["path",{d:"M15 8h-5",key:"1khuty"}],["path",{d:"M19 17V5a2 2 0 0 0-2-2H4",key:"zz82l3"}],["path",{d:"M8 21h12a2 2 0 0 0 2-2v-1a1 1 0 0 0-1-1H11a1 1 0 0 0-1 1v1a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v2a1 1 0 0 0 1 1h3",key:"1ph1d7"}]],m=r("scroll-text",l),d=[{label:"Vue d'ensemble",href:"/maintenance",icon:a.jsx(o,{size:16}),match:"/maintenance"},{label:"Règles",href:"/maintenance/rules",icon:a.jsx(m,{size:16}),match:"/maintenance/rules"},{label:"Historique",href:"/maintenance/history",icon:a.jsx(h,{size:16}),match:"/maintenance/history"}];function u(){const{url:t}=c();return a.jsx("div",{className:"flex flex-wrap items-center gap-2 mb-6",children:d.map(e=>{const n=e.match==="/maintenance"?t==="/maintenance"||t==="/maintenance/":t.startsWith(e.match);return a.jsxs("a",{href:e.href,className:s("flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition",n?"bg-[var(--color-primary)] text-white":"bg-[var(--color-surface)] border border-[var(--color-border)] text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)]"),children:[e.icon," ",e.label]},e.href)})})}export{h as H,u as M};
