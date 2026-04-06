import{r as i,c as m,j as e,u as z,a as $}from"./app-nz0ZV2SQ.js";import{c as p}from"./clsx-B-dksMZM.js";/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const g=(...t)=>t.filter((s,a,r)=>!!s&&s.trim()!==""&&r.indexOf(s)===a).join(" ").trim();/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const A=t=>t.replace(/([a-z0-9])([A-Z])/g,"$1-$2").toLowerCase();/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const S=t=>t.replace(/^([A-Z])|[\s-_]+(\w)/g,(s,a,r)=>r?r.toUpperCase():a.toLowerCase());/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const k=t=>{const s=S(t);return s.charAt(0).toUpperCase()+s.slice(1)};/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */var u={xmlns:"http://www.w3.org/2000/svg",width:24,height:24,viewBox:"0 0 24 24",fill:"none",stroke:"currentColor",strokeWidth:2,strokeLinecap:"round",strokeLinejoin:"round"};/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const L=t=>{for(const s in t)if(s.startsWith("aria-")||s==="role"||s==="title")return!0;return!1},T=i.createContext({}),H=()=>i.useContext(T),q=i.forwardRef(({color:t,size:s,strokeWidth:a,absoluteStrokeWidth:r,className:d="",children:c,iconNode:n,...l},x)=>{const{size:h=24,strokeWidth:y=2,absoluteStrokeWidth:j=!1,color:N="currentColor",className:w=""}=H()??{},M=r??j?Number(a??y)*24/Number(s??h):a??y;return i.createElement("svg",{ref:x,...u,width:s??h??u.width,height:s??h??u.height,stroke:t??N,strokeWidth:M,className:g("lucide",w,d),...!c&&!L(l)&&{"aria-hidden":"true"},...l},[...n.map(([_,C])=>i.createElement(_,C)),...Array.isArray(c)?c:[c]])});/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const o=(t,s)=>{const a=i.forwardRef(({className:r,...d},c)=>i.createElement(q,{ref:c,iconNode:s,className:g(`lucide-${A(k(t))}`,`lucide-${t}`,r),...d}));return a.displayName=k(t),a};/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const V=[["path",{d:"M10.268 21a2 2 0 0 0 3.464 0",key:"vwvbt9"}],["path",{d:"M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326",key:"11g9vi"}]],W=o("bell",V);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const E=[["path",{d:"M10 12h4",key:"a56b0p"}],["path",{d:"M10 8h4",key:"1sr2af"}],["path",{d:"M14 21v-3a2 2 0 0 0-4 0v3",key:"1rgiei"}],["path",{d:"M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2",key:"secmi2"}],["path",{d:"M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16",key:"16ra0t"}]],D=o("building-2",E);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const R=[["path",{d:"M3 3v16a2 2 0 0 0 2 2h16",key:"c24i48"}],["path",{d:"M18 17V9",key:"2bz60n"}],["path",{d:"M13 17V5",key:"1frdt8"}],["path",{d:"M8 17v-3",key:"17ska0"}]],U=o("chart-column",R);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const B=[["path",{d:"m15 18-6-6 6-6",key:"1wnfg3"}]],I=o("chevron-left",B);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const F=[["path",{d:"m9 18 6-6-6-6",key:"mthhwq"}]],O=o("chevron-right",F);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const P=[["path",{d:"M21.801 10A10 10 0 1 1 17 3.335",key:"yps3ct"}],["path",{d:"m9 11 3 3L22 4",key:"1pflzl"}]],X=o("circle-check-big",P);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Z=[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"m15 9-6 6",key:"1uzhvr"}],["path",{d:"m9 9 6 6",key:"z0biqf"}]],K=o("circle-x",Z);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const G=[["rect",{width:"8",height:"4",x:"8",y:"2",rx:"1",ry:"1",key:"tgr4d6"}],["path",{d:"M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2",key:"116196"}],["path",{d:"m9 14 2 2 4-4",key:"df797q"}]],J=o("clipboard-check",G);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Q=[["path",{d:"M12 16h.01",key:"1drbdi"}],["path",{d:"M16 16h.01",key:"1f9h7w"}],["path",{d:"M3 19a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.5a.5.5 0 0 0-.769-.422l-4.462 2.844A.5.5 0 0 1 15 10.5v-2a.5.5 0 0 0-.769-.422L9.77 10.922A.5.5 0 0 1 9 10.5V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2z",key:"1iv0i2"}],["path",{d:"M8 16h.01",key:"18s6g9"}]],Y=o("factory",Q);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ee=[["path",{d:"m6 14 1.5-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.54 6a2 2 0 0 1-1.95 1.5H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3.9a2 2 0 0 1 1.69.9l.81 1.2a2 2 0 0 0 1.67.9H18a2 2 0 0 1 2 2v2",key:"usdka0"}]],te=o("folder-open",ee);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const se=[["path",{d:"M16 10h2",key:"8sgtl7"}],["path",{d:"M16 14h2",key:"epxaof"}],["path",{d:"M6.17 15a3 3 0 0 1 5.66 0",key:"n6f512"}],["circle",{cx:"9",cy:"11",r:"2",key:"yxgjnd"}],["rect",{x:"2",y:"5",width:"20",height:"14",rx:"2",key:"qneu4z"}]],ae=o("id-card",se);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const re=[["rect",{width:"7",height:"9",x:"3",y:"3",rx:"1",key:"10lvy0"}],["rect",{width:"7",height:"5",x:"14",y:"3",rx:"1",key:"16une8"}],["rect",{width:"7",height:"9",x:"14",y:"12",rx:"1",key:"1hutg5"}],["rect",{width:"7",height:"5",x:"3",y:"16",rx:"1",key:"ldoo1y"}]],oe=o("layout-dashboard",re);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ce=[["path",{d:"M3 5h.01",key:"18ugdj"}],["path",{d:"M3 12h.01",key:"nlz23k"}],["path",{d:"M3 19h.01",key:"noohij"}],["path",{d:"M8 5h13",key:"1pao27"}],["path",{d:"M8 12h13",key:"1za7za"}],["path",{d:"M8 19h13",key:"m83p4d"}]],ie=o("list",ce);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ne=[["path",{d:"m16 17 5-5-5-5",key:"1bji2h"}],["path",{d:"M21 12H9",key:"dn1m92"}],["path",{d:"M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4",key:"1uf3rs"}]],le=o("log-out",ne);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const de=[["path",{d:"m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7",key:"132q7q"}],["rect",{x:"2",y:"4",width:"20",height:"16",rx:"2",key:"izxlao"}]],he=o("mail",de);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const xe=[["path",{d:"M4 5h16",key:"1tepv9"}],["path",{d:"M4 12h16",key:"1lakjw"}],["path",{d:"M4 19h16",key:"1djgab"}]],pe=o("menu",xe);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const me=[["path",{d:"M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401",key:"kfwtm"}]],ue=o("moon",me);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ye=[["rect",{x:"16",y:"16",width:"6",height:"6",rx:"1",key:"4q2zg0"}],["rect",{x:"2",y:"16",width:"6",height:"6",rx:"1",key:"8cvhb9"}],["rect",{x:"9",y:"2",width:"6",height:"6",rx:"1",key:"1egb70"}],["path",{d:"M5 16v-3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3",key:"1jsf9p"}],["path",{d:"M12 12V8",key:"2874zd"}]],ke=o("network",ye);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const be=[["circle",{cx:"6",cy:"19",r:"3",key:"1kj8tv"}],["path",{d:"M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15",key:"1d8sl"}],["circle",{cx:"18",cy:"5",r:"3",key:"gq8acd"}]],ge=o("route",be);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const fe=[["path",{d:"M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z",key:"oel41y"}],["path",{d:"m9 12 2 2 4-4",key:"dzmm74"}]],ve=o("shield-check",fe);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const je=[["circle",{cx:"12",cy:"12",r:"4",key:"4exip2"}],["path",{d:"M12 2v2",key:"tus03m"}],["path",{d:"M12 20v2",key:"1lh1kg"}],["path",{d:"m4.93 4.93 1.41 1.41",key:"149t6j"}],["path",{d:"m17.66 17.66 1.41 1.41",key:"ptbguv"}],["path",{d:"M2 12h2",key:"1t8f8n"}],["path",{d:"M20 12h2",key:"1q8mjw"}],["path",{d:"m6.34 17.66-1.41 1.41",key:"1m8zz5"}],["path",{d:"m19.07 4.93-1.41 1.41",key:"1shlcs"}]],Ne=o("sun",je);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const we=[["path",{d:"M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2",key:"wrbu53"}],["path",{d:"M15 18H9",key:"1lyqi6"}],["path",{d:"M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14",key:"lysw3i"}],["circle",{cx:"17",cy:"18",r:"2",key:"332jqn"}],["circle",{cx:"7",cy:"18",r:"2",key:"19iecd"}]],f=o("truck",we);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Me=[["path",{d:"M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2",key:"1yyitq"}],["path",{d:"M16 3.128a4 4 0 0 1 0 7.744",key:"16gr8j"}],["path",{d:"M22 21v-2a4 4 0 0 0-3-3.87",key:"kshegd"}],["circle",{cx:"9",cy:"7",r:"4",key:"nufk8"}]],_e=o("users",Me);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ce=[["path",{d:"M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z",key:"1ngwbx"}]],ze=o("wrench",Ce);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const $e=[["path",{d:"M18 6 6 18",key:"1bl5f8"}],["path",{d:"m6 6 12 12",key:"d8bk6v"}]],v=o("x",$e);function b({item:t,collapsed:s}){const{url:a}=m(),r=t.match?a.startsWith(t.match):a===t.href||a.startsWith(t.href+"/");return e.jsx("li",{children:e.jsxs("a",{href:t.href,className:p("flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm transition-all duration-200","hover:bg-white/10",r?"bg-[var(--color-primary)] text-white shadow-md shadow-[var(--color-primary)]/30":"text-[var(--color-sidebar-text)]",s&&"justify-center px-2"),title:s?t.label:void 0,children:[e.jsx("span",{className:"flex-shrink-0 w-5 h-5",children:t.icon}),!s&&e.jsx("span",{className:"truncate",children:t.label})]})})}function Ae({label:t,collapsed:s}){return s?e.jsx("li",{className:"my-2 border-t border-white/10"}):e.jsx("li",{className:"px-4 pt-4 pb-1",children:e.jsx("span",{className:"text-xs font-semibold uppercase tracking-wider text-white/40",children:t})})}const Se=[{header:"Transport",items:[{label:"Suivi Transport",href:"/transport_tracking",icon:e.jsx(ie,{size:18})},{label:"Dashboard Analytics",href:"/dashboard/trackings",icon:e.jsx(U,{size:18}),match:"/dashboard/trackings"},{label:"Fournisseurs",href:"/providers",icon:e.jsx(Y,{size:18})}]},{header:"Flotte",items:[{label:"Camions",href:"/trucks",icon:e.jsx(f,{size:18})},{label:"Conducteurs",href:"/drivers",icon:e.jsx(ae,{size:18})},{label:"Transporteurs",href:"/transporters",icon:e.jsx(ke,{size:18})}]},{header:"Maintenance",items:[{label:"Tableau de bord",href:"/logistics/dashboard",icon:e.jsx(ze,{size:18}),match:"/logistics"}]},{header:"Administration",items:[{label:"Utilisateurs",href:"/users",icon:e.jsx(_e,{size:18})},{label:"Invitations",href:"/invitations",icon:e.jsx(he,{size:18})},{label:"Roles",href:"/roles",icon:e.jsx(ve,{size:18})},{label:"Projets",href:"/projects",icon:e.jsx(te,{size:18})},{label:"Entites",href:"/entities",icon:e.jsx(D,{size:18})}]}],Le=[{header:"Mon espace",items:[{label:"Checklist quotidien",href:"/drivers/checklist",icon:e.jsx(J,{size:18})},{label:"Mes voyages",href:"/drivers/my-trips",icon:e.jsx(ge,{size:18})},{label:"Mon camion",href:"/drivers/my-truck",icon:e.jsx(f,{size:18})}]}];function Te({collapsed:t,onClose:s,mobileOpen:a}){const{auth:r}=m().props,c=r.roles.includes("Driver")?Le:Se;return e.jsxs(e.Fragment,{children:[a&&e.jsx("div",{className:"fixed inset-0 bg-black/50 z-40 lg:hidden",onClick:s}),e.jsxs("aside",{className:p("fixed top-0 left-0 z-50 h-full bg-[var(--color-sidebar-bg)] transition-all duration-300 flex flex-col",t?"w-[68px]":"w-[260px]",a?"translate-x-0":"-translate-x-full lg:translate-x-0"),children:[e.jsxs("div",{className:"flex items-center justify-between h-16 px-4 border-b border-white/10",children:[!t&&e.jsxs("span",{className:"text-lg font-bold text-white tracking-tight",children:["AMC ",e.jsx("span",{className:"text-[var(--color-primary-light)]",children:"Logistics"})]}),t&&e.jsx("span",{className:"text-lg font-bold text-[var(--color-primary-light)] mx-auto",children:"A"}),e.jsx("button",{onClick:s,className:"lg:hidden text-white/60 hover:text-white p-1",children:e.jsx(v,{size:20})})]}),e.jsx("nav",{className:"flex-1 overflow-y-auto py-3 px-2",children:e.jsxs("ul",{className:"space-y-0.5",children:[e.jsx(b,{item:{label:"Dashboard",href:"/dashboard",icon:e.jsx(oe,{size:18}),match:"/dashboard"},collapsed:t}),c.map(n=>e.jsxs("div",{children:[e.jsx(Ae,{label:n.header,collapsed:t}),n.items.map(l=>e.jsx(b,{item:l,collapsed:t},l.href))]},n.header))]})}),!t&&e.jsx("div",{className:"px-4 py-3 border-t border-white/10",children:e.jsx("p",{className:"text-xs text-white/30 text-center",children:"AMC Travaux SN"})})]})]})}function He({onMenuToggle:t,onSidebarCollapse:s,sidebarCollapsed:a}){var l,x,h;const{auth:r}=m().props,{toggle:d,isDark:c}=z(),n=()=>{$.post("/logout")};return e.jsxs("header",{className:"sticky top-0 z-30 flex items-center justify-between h-16 px-4 lg:px-6 bg-[var(--color-surface)] border-b border-[var(--color-border)] shadow-sm",children:[e.jsxs("div",{className:"flex items-center gap-2",children:[e.jsx("button",{onClick:t,className:"lg:hidden p-2 rounded-lg hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]",children:e.jsx(pe,{size:20})}),e.jsx("button",{onClick:s,className:"hidden lg:flex p-2 rounded-lg hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]",children:a?e.jsx(O,{size:18}):e.jsx(I,{size:18})})]}),e.jsxs("div",{className:"flex items-center gap-1.5",children:[e.jsx("button",{onClick:d,className:"p-2 rounded-lg hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)] transition-colors",title:c?"Light mode":"Dark mode",children:c?e.jsx(Ne,{size:18}):e.jsx(ue,{size:18})}),e.jsx("button",{className:"relative p-2 rounded-lg hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]",children:e.jsx(W,{size:18})}),e.jsxs("div",{className:"flex items-center gap-3 ml-2 pl-3 border-l border-[var(--color-border)]",children:[e.jsxs("div",{className:"hidden sm:block text-right",children:[e.jsx("p",{className:"text-sm font-medium text-[var(--color-text)] leading-tight",children:(l=r.user)==null?void 0:l.name}),e.jsx("p",{className:"text-xs text-[var(--color-text-muted)]",children:r.roles[0]??"User"})]}),e.jsx("div",{className:"w-9 h-9 rounded-full bg-[var(--color-primary)] flex items-center justify-center text-white text-sm font-semibold",children:(h=(x=r.user)==null?void 0:x.name)==null?void 0:h.charAt(0).toUpperCase()}),e.jsx("button",{onClick:n,className:"p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-[var(--color-text-muted)] hover:text-[var(--color-danger)] transition-colors",title:"Logout",children:e.jsx(le,{size:18})})]})]})]})}function qe({message:t,type:s,onClose:a,duration:r=4e3}){const[d,c]=i.useState(!0);return i.useEffect(()=>{const n=setTimeout(()=>{c(!1),setTimeout(a,300)},r);return()=>clearTimeout(n)},[r,a]),e.jsxs("div",{className:p("fixed bottom-6 right-6 z-[100] flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg transition-all duration-300 max-w-sm",d?"translate-y-0 opacity-100":"translate-y-4 opacity-0",s==="success"&&"bg-emerald-600 text-white",s==="error"&&"bg-red-600 text-white"),children:[s==="success"?e.jsx(X,{size:20}):e.jsx(K,{size:20}),e.jsx("p",{className:"text-sm font-medium flex-1",children:t}),e.jsx("button",{onClick:()=>{c(!1),setTimeout(a,300)},className:"opacity-70 hover:opacity-100",children:e.jsx(v,{size:16})})]})}function Ee({children:t,title:s}){const{flash:a}=m().props,[r,d]=i.useState(()=>typeof window>"u"?!1:localStorage.getItem("amc-sidebar-collapsed")==="true"),[c,n]=i.useState(!1),[l,x]=i.useState(null);return i.useEffect(()=>{localStorage.setItem("amc-sidebar-collapsed",String(r))},[r]),i.useEffect(()=>{a.success&&x({message:a.success,type:"success"}),a.error&&x({message:a.error,type:"error"})},[a.success,a.error]),e.jsxs("div",{className:"min-h-screen bg-[var(--color-bg)]",children:[e.jsx(Te,{collapsed:r,onClose:()=>n(!1),mobileOpen:c}),e.jsxs("div",{className:p("transition-all duration-300",r?"lg:ml-[68px]":"lg:ml-[260px]"),children:[e.jsx(He,{onMenuToggle:()=>n(h=>!h),onSidebarCollapse:()=>d(h=>!h),sidebarCollapsed:r}),e.jsxs("main",{className:"p-4 lg:p-6 animate-fade-in",children:[s&&e.jsx("h1",{className:"text-2xl font-bold text-[var(--color-text)] mb-6",children:s}),t]})]}),l&&e.jsx(qe,{message:l.message,type:l.type,onClose:()=>x(null)})]})}function De({children:t,className:s,header:a,padding:r=!0}){return e.jsxs("div",{className:p("bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] shadow-[var(--shadow-sm)] transition-shadow hover:shadow-[var(--shadow-md)]",s),children:[a&&e.jsx("div",{className:"px-5 py-3.5 border-b border-[var(--color-border)]",children:typeof a=="string"?e.jsx("h3",{className:"text-sm font-semibold text-[var(--color-text)]",children:a}):a}),e.jsx("div",{className:p(r&&"p-5"),children:t})]})}export{Ee as A,W as B,De as C,ge as R,f as T,_e as U,ze as W,v as X,J as a,X as b,o as c,I as d,O as e};
