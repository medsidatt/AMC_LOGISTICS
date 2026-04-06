import{r as i,c as u,j as e,u as A,a as $}from"./app-BOC2NgTs.js";import{c as m}from"./clsx-B-dksMZM.js";/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const f=(...t)=>t.filter((s,r,a)=>!!s&&s.trim()!==""&&a.indexOf(s)===r).join(" ").trim();/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const S=t=>t.replace(/([a-z0-9])([A-Z])/g,"$1-$2").toLowerCase();/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const L=t=>t.replace(/^([A-Z])|[\s-_]+(\w)/g,(s,r,a)=>a?a.toUpperCase():r.toLowerCase());/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const b=t=>{const s=L(t);return s.charAt(0).toUpperCase()+s.slice(1)};/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */var p={xmlns:"http://www.w3.org/2000/svg",width:24,height:24,viewBox:"0 0 24 24",fill:"none",stroke:"currentColor",strokeWidth:2,strokeLinecap:"round",strokeLinejoin:"round"};/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const T=t=>{for(const s in t)if(s.startsWith("aria-")||s==="role"||s==="title")return!0;return!1},q=i.createContext({}),W=()=>i.useContext(q),H=i.forwardRef(({color:t,size:s,strokeWidth:r,absoluteStrokeWidth:a,className:d="",children:c,iconNode:n,...l},h)=>{const{size:x=24,strokeWidth:y=2,absoluteStrokeWidth:N=!1,color:w="currentColor",className:M=""}=W()??{},C=a??N?Number(r??y)*24/Number(s??x):r??y;return i.createElement("svg",{ref:h,...p,width:s??x??p.width,height:s??x??p.height,stroke:t??w,strokeWidth:C,className:f("lucide",M,d),...!c&&!T(l)&&{"aria-hidden":"true"},...l},[...n.map(([_,z])=>i.createElement(_,z)),...Array.isArray(c)?c:[c]])});/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const o=(t,s)=>{const r=i.forwardRef(({className:a,...d},c)=>i.createElement(H,{ref:c,iconNode:s,className:f(`lucide-${S(b(t))}`,`lucide-${t}`,a),...d}));return r.displayName=b(t),r};/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const V=[["path",{d:"M10.268 21a2 2 0 0 0 3.464 0",key:"vwvbt9"}],["path",{d:"M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326",key:"11g9vi"}]],E=o("bell",V);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const D=[["path",{d:"M3 3v16a2 2 0 0 0 2 2h16",key:"c24i48"}],["path",{d:"M18 17V9",key:"2bz60n"}],["path",{d:"M13 17V5",key:"1frdt8"}],["path",{d:"M8 17v-3",key:"17ska0"}]],R=o("chart-column",D);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const U=[["path",{d:"m15 18-6-6 6-6",key:"1wnfg3"}]],I=o("chevron-left",U);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const B=[["path",{d:"m9 18 6-6-6-6",key:"mthhwq"}]],F=o("chevron-right",B);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const O=[["path",{d:"M21.801 10A10 10 0 1 1 17 3.335",key:"yps3ct"}],["path",{d:"m9 11 3 3L22 4",key:"1pflzl"}]],P=o("circle-check-big",O);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const X=[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"m15 9-6 6",key:"1uzhvr"}],["path",{d:"m9 9 6 6",key:"z0biqf"}]],Z=o("circle-x",X);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const K=[["rect",{width:"8",height:"4",x:"8",y:"2",rx:"1",ry:"1",key:"tgr4d6"}],["path",{d:"M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2",key:"116196"}],["path",{d:"m9 14 2 2 4-4",key:"df797q"}]],G=o("clipboard-check",K);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const J=[["path",{d:"M12 16h.01",key:"1drbdi"}],["path",{d:"M16 16h.01",key:"1f9h7w"}],["path",{d:"M3 19a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.5a.5.5 0 0 0-.769-.422l-4.462 2.844A.5.5 0 0 1 15 10.5v-2a.5.5 0 0 0-.769-.422L9.77 10.922A.5.5 0 0 1 9 10.5V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2z",key:"1iv0i2"}],["path",{d:"M8 16h.01",key:"18s6g9"}]],Q=o("factory",J);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Y=[["path",{d:"M16 10h2",key:"8sgtl7"}],["path",{d:"M16 14h2",key:"epxaof"}],["path",{d:"M6.17 15a3 3 0 0 1 5.66 0",key:"n6f512"}],["circle",{cx:"9",cy:"11",r:"2",key:"yxgjnd"}],["rect",{x:"2",y:"5",width:"20",height:"14",rx:"2",key:"qneu4z"}]],ee=o("id-card",Y);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const te=[["rect",{width:"7",height:"9",x:"3",y:"3",rx:"1",key:"10lvy0"}],["rect",{width:"7",height:"5",x:"14",y:"3",rx:"1",key:"16une8"}],["rect",{width:"7",height:"9",x:"14",y:"12",rx:"1",key:"1hutg5"}],["rect",{width:"7",height:"5",x:"3",y:"16",rx:"1",key:"ldoo1y"}]],se=o("layout-dashboard",te);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const re=[["path",{d:"M3 5h.01",key:"18ugdj"}],["path",{d:"M3 12h.01",key:"nlz23k"}],["path",{d:"M3 19h.01",key:"noohij"}],["path",{d:"M8 5h13",key:"1pao27"}],["path",{d:"M8 12h13",key:"1za7za"}],["path",{d:"M8 19h13",key:"m83p4d"}]],ae=o("list",re);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const oe=[["path",{d:"m16 17 5-5-5-5",key:"1bji2h"}],["path",{d:"M21 12H9",key:"dn1m92"}],["path",{d:"M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4",key:"1uf3rs"}]],ce=o("log-out",oe);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ie=[["path",{d:"m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7",key:"132q7q"}],["rect",{x:"2",y:"4",width:"20",height:"16",rx:"2",key:"izxlao"}]],ne=o("mail",ie);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const le=[["path",{d:"M4 5h16",key:"1tepv9"}],["path",{d:"M4 12h16",key:"1lakjw"}],["path",{d:"M4 19h16",key:"1djgab"}]],de=o("menu",le);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const he=[["path",{d:"M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401",key:"kfwtm"}]],xe=o("moon",he);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const me=[["rect",{x:"16",y:"16",width:"6",height:"6",rx:"1",key:"4q2zg0"}],["rect",{x:"2",y:"16",width:"6",height:"6",rx:"1",key:"8cvhb9"}],["rect",{x:"9",y:"2",width:"6",height:"6",rx:"1",key:"1egb70"}],["path",{d:"M5 16v-3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3",key:"1jsf9p"}],["path",{d:"M12 12V8",key:"2874zd"}]],ue=o("network",me);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const pe=[["circle",{cx:"6",cy:"19",r:"3",key:"1kj8tv"}],["path",{d:"M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15",key:"1d8sl"}],["circle",{cx:"18",cy:"5",r:"3",key:"gq8acd"}]],ye=o("route",pe);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const be=[["path",{d:"M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z",key:"oel41y"}],["path",{d:"m9 12 2 2 4-4",key:"dzmm74"}]],ke=o("shield-check",be);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ve=[["circle",{cx:"12",cy:"12",r:"4",key:"4exip2"}],["path",{d:"M12 2v2",key:"tus03m"}],["path",{d:"M12 20v2",key:"1lh1kg"}],["path",{d:"m4.93 4.93 1.41 1.41",key:"149t6j"}],["path",{d:"m17.66 17.66 1.41 1.41",key:"ptbguv"}],["path",{d:"M2 12h2",key:"1t8f8n"}],["path",{d:"M20 12h2",key:"1q8mjw"}],["path",{d:"m6.34 17.66-1.41 1.41",key:"1m8zz5"}],["path",{d:"m19.07 4.93-1.41 1.41",key:"1shlcs"}]],fe=o("sun",ve);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ge=[["path",{d:"M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2",key:"wrbu53"}],["path",{d:"M15 18H9",key:"1lyqi6"}],["path",{d:"M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14",key:"lysw3i"}],["circle",{cx:"17",cy:"18",r:"2",key:"332jqn"}],["circle",{cx:"7",cy:"18",r:"2",key:"19iecd"}]],g=o("truck",ge);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const je=[["path",{d:"M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2",key:"1yyitq"}],["path",{d:"M16 3.128a4 4 0 0 1 0 7.744",key:"16gr8j"}],["path",{d:"M22 21v-2a4 4 0 0 0-3-3.87",key:"kshegd"}],["circle",{cx:"9",cy:"7",r:"4",key:"nufk8"}]],Ne=o("users",je);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const we=[["path",{d:"M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z",key:"1ngwbx"}]],Me=o("wrench",we);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ce=[["path",{d:"M18 6 6 18",key:"1bl5f8"}],["path",{d:"m6 6 12 12",key:"d8bk6v"}]],j=o("x",Ce);function k({item:t,collapsed:s}){const{url:r}=u(),a=t.match?r.startsWith(t.match):r===t.href||r.startsWith(t.href+"/");return e.jsx("li",{children:e.jsxs("a",{href:t.href,className:m("flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm transition-all duration-200",a?"bg-[var(--color-primary)] text-white shadow-md shadow-[var(--color-primary)]/30":"text-[var(--color-sidebar-text)] hover:bg-[var(--color-sidebar-hover)]",s&&"justify-center px-2"),title:s?t.label:void 0,children:[e.jsx("span",{className:"flex-shrink-0 w-5 h-5",children:t.icon}),!s&&e.jsx("span",{className:"truncate",children:t.label})]})})}function _e({label:t,collapsed:s}){return s?e.jsx("li",{className:"my-2 border-t border-[var(--color-sidebar-border)]"}):e.jsx("li",{className:"px-4 pt-4 pb-1",children:e.jsx("span",{className:"text-xs font-semibold uppercase tracking-wider text-[var(--color-sidebar-muted)]",children:t})})}const v=[{header:"Transport",items:[{label:"Suivi Transport",href:"/transport_tracking",icon:e.jsx(ae,{size:18})},{label:"Dashboard Analytics",href:"/dashboard/trackings",icon:e.jsx(R,{size:18}),match:"/dashboard/trackings"},{label:"Fournisseurs",href:"/providers",icon:e.jsx(Q,{size:18})}]},{header:"Flotte",items:[{label:"Camions",href:"/trucks",icon:e.jsx(g,{size:18})},{label:"Conducteurs",href:"/drivers",icon:e.jsx(ee,{size:18})},{label:"Transporteurs",href:"/transporters",icon:e.jsx(ue,{size:18})}]},{header:"Maintenance",items:[{label:"Tableau de bord",href:"/logistics/dashboard",icon:e.jsx(Me,{size:18}),match:"/logistics"}]}],ze={header:"Administration",items:[{label:"Utilisateurs",href:"/users",icon:e.jsx(Ne,{size:18})},{label:"Invitations",href:"/auth/invitations",icon:e.jsx(ne,{size:18}),match:"/auth/invitations"},{label:"Rôles",href:"/roles",icon:e.jsx(ke,{size:18})}]},Ae=[{header:"Mon espace",items:[{label:"Checklist quotidien",href:"/drivers/checklist",icon:e.jsx(G,{size:18})},{label:"Mes voyages",href:"/drivers/my-trips",icon:e.jsx(ye,{size:18})},{label:"Mon camion",href:"/drivers/my-truck",icon:e.jsx(g,{size:18})}]}];function $e({collapsed:t,onClose:s,mobileOpen:r}){const{auth:a}=u().props,d=a.roles.includes("Driver"),c=a.roles.includes("Admin")||a.roles.includes("Super Admin");let n;return d?n=Ae:c?n=[...v,ze]:n=v,e.jsxs(e.Fragment,{children:[r&&e.jsx("div",{className:"fixed inset-0 bg-black/50 z-40 lg:hidden",onClick:s}),e.jsxs("aside",{className:m("fixed top-0 left-0 z-50 h-full bg-[var(--color-sidebar-bg)] transition-all duration-300 flex flex-col",t?"w-[68px]":"w-[260px]",r?"translate-x-0":"-translate-x-full lg:translate-x-0"),children:[e.jsxs("div",{className:"flex items-center justify-between h-16 px-4 border-b border-[var(--color-sidebar-border)]",children:[!t&&e.jsxs("span",{className:"text-lg font-bold text-[var(--color-sidebar-title)] tracking-tight",children:["AMC ",e.jsx("span",{className:"text-[var(--color-primary)]",children:"Logistics"})]}),t&&e.jsx("span",{className:"text-lg font-bold text-[var(--color-primary)] mx-auto",children:"A"}),e.jsx("button",{onClick:s,className:"lg:hidden text-[var(--color-sidebar-muted)] hover:text-[var(--color-sidebar-text)] p-1",children:e.jsx(j,{size:20})})]}),e.jsx("nav",{className:"flex-1 overflow-y-auto py-3 px-2",children:e.jsxs("ul",{className:"space-y-0.5",children:[e.jsx(k,{item:{label:"Dashboard",href:"/dashboard",icon:e.jsx(se,{size:18}),match:"/dashboard"},collapsed:t}),n.map(l=>e.jsxs("div",{children:[e.jsx(_e,{label:l.header,collapsed:t}),l.items.map(h=>e.jsx(k,{item:h,collapsed:t},h.href))]},l.header))]})}),!t&&e.jsx("div",{className:"px-4 py-3 border-t border-[var(--color-sidebar-border)]",children:e.jsx("p",{className:"text-xs text-[var(--color-sidebar-muted)] text-center",children:"AMC Travaux SN"})})]})]})}function Se({onMenuToggle:t,onSidebarCollapse:s,sidebarCollapsed:r}){var l,h,x;const{auth:a}=u().props,{toggle:d,isDark:c}=A(),n=()=>{$.post("/logout")};return e.jsxs("header",{className:"sticky top-0 z-30 flex items-center justify-between h-16 px-4 lg:px-6 bg-[var(--color-surface)] border-b border-[var(--color-border)] shadow-sm",children:[e.jsxs("div",{className:"flex items-center gap-2",children:[e.jsx("button",{onClick:t,className:"lg:hidden p-2 rounded-lg hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]",children:e.jsx(de,{size:20})}),e.jsx("button",{onClick:s,className:"hidden lg:flex p-2 rounded-lg hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]",children:r?e.jsx(F,{size:18}):e.jsx(I,{size:18})})]}),e.jsxs("div",{className:"flex items-center gap-1.5",children:[e.jsx("button",{onClick:d,className:"p-2 rounded-lg hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)] transition-colors",title:c?"Light mode":"Dark mode",children:c?e.jsx(fe,{size:18}):e.jsx(xe,{size:18})}),e.jsx("button",{className:"relative p-2 rounded-lg hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]",children:e.jsx(E,{size:18})}),e.jsxs("div",{className:"flex items-center gap-3 ml-2 pl-3 border-l border-[var(--color-border)]",children:[e.jsxs("div",{className:"hidden sm:block text-right",children:[e.jsx("p",{className:"text-sm font-medium text-[var(--color-text)] leading-tight",children:(l=a.user)==null?void 0:l.name}),e.jsx("p",{className:"text-xs text-[var(--color-text-muted)]",children:a.roles[0]??"User"})]}),e.jsx("div",{className:"w-9 h-9 rounded-full bg-[var(--color-primary)] flex items-center justify-center text-white text-sm font-semibold",children:(x=(h=a.user)==null?void 0:h.name)==null?void 0:x.charAt(0).toUpperCase()}),e.jsx("button",{onClick:n,className:"p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-[var(--color-text-muted)] hover:text-[var(--color-danger)] transition-colors",title:"Logout",children:e.jsx(ce,{size:18})})]})]})]})}function Le({message:t,type:s,onClose:r,duration:a=4e3}){const[d,c]=i.useState(!0);return i.useEffect(()=>{const n=setTimeout(()=>{c(!1),setTimeout(r,300)},a);return()=>clearTimeout(n)},[a,r]),e.jsxs("div",{className:m("fixed bottom-6 right-6 z-[100] flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg transition-all duration-300 max-w-sm",d?"translate-y-0 opacity-100":"translate-y-4 opacity-0",s==="success"&&"bg-emerald-600 text-white",s==="error"&&"bg-red-600 text-white"),children:[s==="success"?e.jsx(P,{size:20}):e.jsx(Z,{size:20}),e.jsx("p",{className:"text-sm font-medium flex-1",children:t}),e.jsx("button",{onClick:()=>{c(!1),setTimeout(r,300)},className:"opacity-70 hover:opacity-100",children:e.jsx(j,{size:16})})]})}function We({children:t,title:s}){const{flash:r}=u().props,[a,d]=i.useState(()=>typeof window>"u"?!1:localStorage.getItem("amc-sidebar-collapsed")==="true"),[c,n]=i.useState(!1),[l,h]=i.useState(null);return i.useEffect(()=>{localStorage.setItem("amc-sidebar-collapsed",String(a))},[a]),i.useEffect(()=>{r.success&&h({message:r.success,type:"success"}),r.error&&h({message:r.error,type:"error"})},[r.success,r.error]),e.jsxs("div",{className:"min-h-screen bg-[var(--color-bg)]",children:[e.jsx($e,{collapsed:a,onClose:()=>n(!1),mobileOpen:c}),e.jsxs("div",{className:m("transition-all duration-300",a?"lg:ml-[68px]":"lg:ml-[260px]"),children:[e.jsx(Se,{onMenuToggle:()=>n(x=>!x),onSidebarCollapse:()=>d(x=>!x),sidebarCollapsed:a}),e.jsxs("main",{className:"p-4 lg:p-6 animate-fade-in",children:[s&&e.jsx("h1",{className:"text-2xl font-bold text-[var(--color-text)] mb-6",children:s}),t]})]}),l&&e.jsx(Le,{message:l.message,type:l.type,onClose:()=>h(null)})]})}function He({children:t,className:s,header:r,padding:a=!0}){return e.jsxs("div",{className:m("bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] shadow-[var(--shadow-sm)] transition-shadow hover:shadow-[var(--shadow-md)]",s),children:[r&&e.jsx("div",{className:"px-5 py-3.5 border-b border-[var(--color-border)]",children:typeof r=="string"?e.jsx("h3",{className:"text-sm font-semibold text-[var(--color-text)]",children:r}):r}),e.jsx("div",{className:m(a&&"p-5"),children:t})]})}export{We as A,E as B,He as C,ye as R,g as T,Ne as U,Me as W,j as X,G as a,P as b,o as c,I as d,F as e};
