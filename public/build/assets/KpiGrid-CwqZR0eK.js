import{j as s,r as m}from"./app-D4_8Aybb.js";import{c as N}from"./clsx-B-dksMZM.js";import{d as g,f as y}from"./formatters-CpoeePTG.js";import{c as x}from"./shield-alert-Bfbo7O91.js";/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const b=[["path",{d:"M5 12h14",key:"1ays0h"}]],w=x("minus",b);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const k=[["path",{d:"M16 17h6v-6",key:"t6n2it"}],["path",{d:"m22 17-8.5-8.5-5 5L2 7",key:"x473p"}]],M=x("trending-down",k);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const _=[["path",{d:"M16 7h6v6",key:"box55l"}],["path",{d:"m22 7-8.5 8.5-5-5L2 17",key:"1t1m79"}]],T=x("trending-up",_);function C({value:r,decimals:o=0}){const t=Number(r)||0,[e,n]=m.useState(0),i=m.useRef(0);return m.useEffect(()=>{const a=i.current,c=t-a,d=600,l=performance.now(),u=f=>{const v=f-l,p=Math.min(v/d,1),h=1-Math.pow(1-p,3),j=a+c*h;n(j),p<1?requestAnimationFrame(u):i.current=t};requestAnimationFrame(u)},[t]),s.jsx(s.Fragment,{children:y(e,o)})}function K({label:r,value:o,unit:t,change:e,changeLabel:n,icon:i,color:a="var(--color-primary)",decimals:c=0}){const d=e===void 0||e===0?w:e>0?T:M,l=e===void 0||e===0?"text-[var(--color-text-muted)]":e>0?"text-emerald-500":"text-red-500";return s.jsx("div",{className:"bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] p-5 shadow-[var(--shadow-sm)] hover:shadow-[var(--shadow-md)] transition-all duration-300 animate-slide-up",children:s.jsxs("div",{className:"flex items-start justify-between",children:[s.jsxs("div",{className:"flex-1",children:[s.jsx("p",{className:"text-xs font-medium uppercase tracking-wider text-[var(--color-text-muted)] mb-2",children:r}),s.jsxs("div",{className:"flex items-baseline gap-1.5",children:[s.jsx("span",{className:"text-2xl font-bold text-[var(--color-text)]",children:s.jsx(C,{value:o,decimals:c})}),t&&s.jsx("span",{className:"text-sm font-medium text-[var(--color-text-secondary)]",children:t})]}),e!==void 0&&s.jsxs("div",{className:N("flex items-center gap-1 mt-2",l),children:[s.jsx(d,{size:14}),s.jsx("span",{className:"text-xs font-medium",children:g(e)}),n&&s.jsx("span",{className:"text-xs text-[var(--color-text-muted)]",children:n})]})]}),s.jsx("div",{className:"flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center",style:{background:`${a}15`,color:a},children:i})]})})}function q({children:r,columns:o=4}){const t={2:"grid-cols-1 sm:grid-cols-2",3:"grid-cols-1 sm:grid-cols-2 lg:grid-cols-3",4:"grid-cols-1 sm:grid-cols-2 lg:grid-cols-4"}[o];return s.jsx("div",{className:`grid gap-4 ${t}`,children:r})}export{q as K,M as T,K as a,T as b};
