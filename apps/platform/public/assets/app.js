const state={token:'',csrf:sessionStorage.getItem('fanoos_csrf')||'',workspace:sessionStorage.getItem('fanoos_workspace')||'',account:null};
const $=selector=>document.querySelector(selector);

async function api(path,options={}){
  const headers={'Content-Type':'application/json',...(options.headers||{})};
  if(state.token)headers.Authorization=`Bearer ${state.token}`;
  if(options.method&&options.method!=='GET')headers['X-CSRF-Token']=state.csrf;
  const response=await fetch(path,{...options,headers});
  const payload=await response.json();
  if(!response.ok)throw new Error(payload.error?.message||'خطایی رخ داد.');
  return payload.data;
}

function text(value){return value===null||value===undefined?'—':String(value)}
function renderRows(rows){
  const list=$('#result-list');list.replaceChildren();
  if(!Array.isArray(rows)||rows.length===0){list.innerHTML='<div class="empty-state">موردی برای نمایش نیست.</div>';return}
  rows.forEach(row=>{const card=document.createElement('article');card.className='result-card';const title=document.createElement('h3');title.textContent=text(row.title||row.course_title||row.item_title||row.product_name_snapshot||row.source_type||'جزئیات');const detail=document.createElement('p');detail.textContent=Object.entries(row).filter(([key])=>!['title','body','data_json','schema_json'].includes(key)).slice(0,5).map(([key,value])=>`${key}: ${text(value)}`).join(' · ');card.append(title,detail);list.append(card)})
}
function showAccount(account){
  state.account=account;$('#login-panel').hidden=true;$('#dashboard').hidden=false;$('#logout').hidden=false;$('#workspace-picker').hidden=false;$('#greeting').textContent=`سلام ${account.user.display_name}`;
  const select=$('#workspace-select');select.replaceChildren();account.workspaces.forEach(workspace=>{const option=document.createElement('option');option.value=workspace.id;option.textContent=workspace.name;select.append(option)});
  const available=account.workspaces.some(item=>item.id===state.workspace);state.workspace=available?state.workspace:(account.selected_workspace_id||account.workspaces[0]?.id||'');select.value=state.workspace;updatePath();
}
function updatePath(){const item=state.account?.workspaces.find(workspace=>workspace.id===state.workspace);$('#workspace-path').textContent=item?[item.institution_name,item.faculty_name,item.program_name,item.cohort_label].filter(Boolean).join(' / '):'فضایی انتخاب نشده';sessionStorage.setItem('fanoos_workspace',state.workspace)}

const views={
  schedule:{title:'برنامه و آزمون‌ها',path:()=>`/api/v1/workspaces/${state.workspace}/schedule?from=${new Date().toISOString().slice(0,10)}&to=${new Date(Date.now()+90*86400000).toISOString().slice(0,10)}`},
  grades:{title:'مرکز نمرات',path:()=>`/api/v1/workspaces/${state.workspace}/grades/me`},announcements:{title:'اطلاعیه‌ها',path:()=>`/api/v1/workspaces/${state.workspace}/announcements`},academics:{title:'درس‌ها و جلسه‌ها',path:()=>`/api/v1/workspaces/${state.workspace}/academics`,pick:data=>data.courses},forms:{title:'فرم‌های فعال',path:()=>`/api/v1/workspaces/${state.workspace}/forms`},orders:{title:'خریدهای من',path:()=>`/api/v1/workspaces/${state.workspace}/orders`},
  resources:{title:'کتابخانه منابع',path:()=>`/api/v1/workspaces/${state.workspace}/resources?sort=newest`},
  assessments:{title:'تمرین و آزمون',path:()=>`/api/v1/workspaces/${state.workspace}/assessments`}
};
async function loadView(key){const view=views[key];if(!view||!state.workspace)return;document.querySelectorAll('[data-view]').forEach(button=>button.classList.toggle('active',button.dataset.view===key));$('#view-title').textContent=view.title;$('#result-list').innerHTML='<div class="empty-state">در حال دریافت…</div>';try{const data=await api(view.path());renderRows(view.pick?view.pick(data):data)}catch(error){$('#result-list').innerHTML='';renderRows([{title:'دریافت اطلاعات ممکن نشد',message:error.message}])}}

$('#login-form').addEventListener('submit',async event=>{event.preventDefault();const button=event.currentTarget.querySelector('button');button.disabled=true;$('#login-message').textContent='';try{const fields=new FormData(event.currentTarget);const data=await api('/api/v1/auth/login',{method:'POST',body:JSON.stringify({identifier:fields.get('identifier'),password:fields.get('password')})});state.token=data.token;state.csrf=data.csrf_token;sessionStorage.setItem('fanoos_csrf',state.csrf);showAccount(data.account);await loadView('schedule')}catch(error){$('#login-message').textContent=error.message}finally{button.disabled=false}});
$('#logout').addEventListener('click',async()=>{try{await api('/api/v1/auth/logout',{method:'POST'})}catch{}sessionStorage.clear();location.reload()});
$('#workspace-select').addEventListener('change',async event=>{const prior=state.workspace;try{await api('/api/v1/workspaces/select',{method:'POST',body:JSON.stringify({workspace_id:event.target.value})});state.workspace=event.target.value;updatePath();await loadView('schedule')}catch(error){event.target.value=prior;alert(error.message)}});
document.querySelectorAll('[data-view]').forEach(button=>button.addEventListener('click',()=>loadView(button.dataset.view)));
$('#search-form').addEventListener('submit',async event=>{event.preventDefault();const q=new FormData(event.currentTarget).get('q');if(!state.workspace)return;$('#view-title').textContent='نتایج جست‌وجو';try{renderRows(await api(`/api/v1/workspaces/${state.workspace}/search?q=${encodeURIComponent(q)}`))}catch(error){renderRows([{title:'جست‌وجو انجام نشد',message:error.message}])}});
$('#today').textContent=new Intl.DateTimeFormat('fa-IR',{dateStyle:'full'}).format(new Date());
api('/api/v1/account').then(async account=>{showAccount(account);await loadView('schedule')}).catch(()=>{});
