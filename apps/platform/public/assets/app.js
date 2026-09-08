const state={
  token:'',
  csrf:sessionStorage.getItem('fanoos_csrf')||'',
  workspace:'',
  view:sessionStorage.getItem('fanoos_view')||'schedule',
  account:null,
  requestSerial:0,
  workspaceMutation:false
};
const $=selector=>document.querySelector(selector);

class ApiError extends Error{
  constructor(code,status){super('API request failed');this.name='ApiError';this.code=code||'request_failed';this.status=status||0}
}

async function api(path,options={}){
  const method=(options.method||'GET').toUpperCase();
  const headers={'Content-Type':'application/json',...(options.headers||{})};
  if(state.token)headers.Authorization=`Bearer ${state.token}`;
  if(method!=='GET')headers['X-CSRF-Token']=state.csrf;
  let response;
  try{response=await fetch(path,{...options,method,headers})}catch{throw new ApiError('network_error',0)}
  let payload={};
  try{payload=await response.json()}catch{payload={}}
  if(!response.ok)throw new ApiError(payload?.error?.code||'request_failed',response.status);
  return payload.data;
}

function safeAuthMessage(error){
  const code=error&&error.code;
  if(['invalid_credentials','authentication_failed','invalid_login'].includes(code))return 'شناسه یا رمز عبور درست نیست.';
  if(code==='rate_limited')return 'تعداد تلاش‌ها زیاد بوده است. کمی بعد دوباره امتحان کنید.';
  return 'ورود انجام نشد. اطلاعات را بررسی کنید و دوباره تلاش کنید.';
}

function currentWorkspace(){return state.account?.workspaces?.find(workspace=>workspace.id===state.workspace)||null}

function workspaceTimezone(){
  const value=currentWorkspace()?.timezone_name;
  return typeof value==='string'&&value.trim()?value.trim():''
}

function dateKeyInTimezone(date,timeZone){
  try{
    const parts=new Intl.DateTimeFormat('en-US',{timeZone:timeZone||'UTC',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(date);
    const values=Object.fromEntries(parts.map(part=>[part.type,part.value]));
    if(values.year&&values.month&&values.day)return `${values.year}-${values.month}-${values.day}`
  }catch{}
  return date.toISOString().slice(0,10)
}

function addCalendarDays(dateKey,days){
  const [year,month,day]=dateKey.split('-').map(Number);
  const date=new Date(Date.UTC(year,month-1,day+days,12,0,0));
  return date.toISOString().slice(0,10)
}

function scheduleRange(){
  const today=dateKeyInTimezone(new Date(),workspaceTimezone()||'UTC');
  return {from:today,to:addCalendarDays(today,90)}
}

function updateToday(){
  const options={dateStyle:'full'};
  const timeZone=workspaceTimezone();
  if(timeZone)options.timeZone=timeZone;
  try{$('#today').textContent=new Intl.DateTimeFormat('fa-IR',options).format(new Date())}
  catch{$('#today').textContent=new Intl.DateTimeFormat('fa-IR',{dateStyle:'full'}).format(new Date())}
}

function updatePath(){
  const item=currentWorkspace();
  $('#workspace-path').textContent=item?[item.institution_name,item.faculty_name,item.program_name,item.cohort_label].filter(Boolean).join(' / '):'فضایی انتخاب نشده';
  if(state.workspace)sessionStorage.setItem('fanoos_workspace',state.workspace);else sessionStorage.removeItem('fanoos_workspace');
  updateToday()
}

function showAccount(account,{focus=false}={}){
  state.account=account;
  $('#login-panel').hidden=true;$('#dashboard').hidden=false;$('#logout').hidden=false;$('#workspace-picker').hidden=false;
  $('#greeting').textContent=`سلام ${account?.user?.display_name||''}`.trim();
  const select=$('#workspace-select');select.replaceChildren();
  const placeholder=document.createElement('option');placeholder.value='';placeholder.textContent='انتخاب فضای آموزشی';placeholder.disabled=true;select.append(placeholder);
  const workspaces=Array.isArray(account?.workspaces)?account.workspaces:[];
  workspaces.forEach(workspace=>{const option=document.createElement('option');option.value=workspace.id;option.textContent=workspace.name;select.append(option)});
  const canonical=typeof account?.selected_workspace_id==='string'&&workspaces.some(item=>item.id===account.selected_workspace_id)?account.selected_workspace_id:'';
  state.workspace=canonical;
  select.value=canonical;updatePath();
  if(focus)$('#dashboard').focus()
}

const views={
  schedule:{title:'برنامه',path:()=>{const range=scheduleRange();return `/api/v1/workspaces/${state.workspace}/schedule?from=${range.from}&to=${range.to}`}},
  grades:{title:'نمرات',path:()=>`/api/v1/workspaces/${state.workspace}/grades/me`},
  announcements:{title:'اطلاعیه‌ها',path:()=>`/api/v1/workspaces/${state.workspace}/announcements`},
  academics:{title:'فضای آموزشی',path:()=>`/api/v1/workspaces/${state.workspace}/academics`,pick:data=>data?.courses},
  resources:{title:'منابع',path:()=>`/api/v1/workspaces/${state.workspace}/resources?sort=newest`},
  assessments:{title:'تمرین و آزمون',path:()=>`/api/v1/workspaces/${state.workspace}/assessments`},
  forms:{title:'فرم‌ها',path:()=>`/api/v1/workspaces/${state.workspace}/forms`},
  orders:{title:'خرید و دسترسی',path:()=>`/api/v1/workspaces/${state.workspace}/orders`}
};

function setActiveView(key){
  document.querySelectorAll('[data-view]').forEach(button=>{
    const active=button.dataset.view===key;
    button.classList.toggle('active',active);
    button.setAttribute('aria-pressed',active?'true':'false')
  });
  state.view=key;sessionStorage.setItem('fanoos_view',key)
}

function clearActiveView(){
  document.querySelectorAll('[data-view]').forEach(button=>{
    button.classList.remove('active');
    button.setAttribute('aria-pressed','false')
  })
}

function renderContext(extra={}){
  return {workspaceName:currentWorkspace()?.name||'',workspaceTimezone:workspaceTimezone(),...extra}
}

async function loadView(key){
  const Domain=window.FanoosDomainUX;const view=views[key];
  if(!view||state.workspaceMutation)return;
  setActiveView(key);$('#view-title').textContent=view.title;
  if(!state.workspace){Domain.renderEmpty($('#result-list'),key,'برای مشاهده اطلاعات، ابتدا یک فضای آموزشی انتخاب کنید.');return}
  const serial=++state.requestSerial;Domain.renderLoading($('#result-list'));
  try{
    const data=await api(view.path());if(serial!==state.requestSerial)return;
    Domain.renderView(key,$('#result-list'),view.pick?view.pick(data):data,renderContext())
  }catch(error){
    if(serial!==state.requestSerial)return;
    Domain.renderError($('#result-list'),{onRetry:()=>loadView(key)})
  }
}

async function runSearch(query){
  const Domain=window.FanoosDomainUX;const form=$('#search-form');const button=form.querySelector('button');
  const q=Domain.normalizeText(query);if(!state.workspace||state.workspaceMutation||q.length<2)return;
  const serial=++state.requestSerial;
  $('#view-title').textContent='نتایج جست‌وجو';clearActiveView();
  sessionStorage.setItem('fanoos_search_query',q);button.disabled=true;Domain.renderLoading($('#result-list'),'در حال جست‌وجو…');
  try{
    const rows=await api(`/api/v1/workspaces/${state.workspace}/search?q=${encodeURIComponent(q)}`);if(serial!==state.requestSerial)return;
    Domain.renderView('search',$('#result-list'),rows,renderContext({query:q}))
  }catch(error){
    if(serial!==state.requestSerial)return;
    Domain.renderError($('#result-list'),{title:'جست‌وجو انجام نشد',onRetry:()=>runSearch(q)})
  }finally{button.disabled=false}
}

function bindInteractions(){
  document.querySelectorAll('[data-view]').forEach(button=>button.setAttribute('aria-pressed','false'));

  $('#login-form').addEventListener('submit',async event=>{
    event.preventDefault();const button=event.currentTarget.querySelector('button');if(button.disabled)return;
    button.disabled=true;$('#login-message').textContent='';
    try{
      const fields=new FormData(event.currentTarget);
      const data=await api('/api/v1/auth/login',{method:'POST',body:JSON.stringify({identifier:fields.get('identifier'),password:fields.get('password')})});
      state.token=data.token;state.csrf=data.csrf_token;sessionStorage.setItem('fanoos_csrf',state.csrf);showAccount(data.account,{focus:true});
      await loadView(views[state.view]?state.view:'schedule')
    }catch(error){$('#login-message').textContent=safeAuthMessage(error)}finally{button.disabled=false}
  });

  $('#logout').addEventListener('click',async event=>{
    const button=event.currentTarget;if(button.disabled)return;button.disabled=true;++state.requestSerial;
    try{
      await api('/api/v1/auth/logout',{method:'POST'});
      sessionStorage.clear();location.reload()
    }catch{
      window.FanoosDomainUX.renderError($('#result-list'),{title:'خروج انجام نشد',message:'اتصال با سامانه برقرار نشد. حساب شما همچنان فعال است؛ دوباره تلاش کنید.'});
      button.disabled=false
    }
  });

  $('#workspace-select').addEventListener('change',async event=>{
    if(state.workspaceMutation)return;
    const select=event.currentTarget;const prior=state.workspace;const next=select.value;
    if(!next||next===prior){select.value=prior;return}
    state.workspaceMutation=true;select.disabled=true;++state.requestSerial;
    let changed=false;
    try{
      await api('/api/v1/workspaces/select',{method:'POST',body:JSON.stringify({workspace_id:next})});
      state.workspace=next;updatePath();changed=true
    }catch{
      select.value=prior;
      window.FanoosDomainUX.renderError($('#result-list'),{title:'تغییر فضای آموزشی انجام نشد',message:'فضای قبلی همچنان فعال است. دوباره تلاش کنید.'})
    }finally{state.workspaceMutation=false;select.disabled=false}
    if(changed)await loadView(views[state.view]?state.view:'schedule')
  });

  document.querySelectorAll('[data-view]').forEach(button=>button.addEventListener('click',()=>loadView(button.dataset.view)));

  $('#search-form').addEventListener('submit',event=>{
    event.preventDefault();const fields=new FormData(event.currentTarget);runSearch(fields.get('q'))
  })
}

async function boot(){
  if(!window.FanoosDomainUX)throw new Error('domain UX unavailable');
  bindInteractions();
  const savedSearch=sessionStorage.getItem('fanoos_search_query')||'';if(savedSearch)$('#search-form [name="q"]').value=savedSearch;
  updateToday();
  try{
    const account=await api('/api/v1/account');showAccount(account);await loadView(views[state.view]?state.view:'schedule')
  }catch{}
}

boot().catch(()=>{
  const message=$('#login-message');if(message)message.textContent='رابط کاربری کامل بارگذاری نشد. صفحه را دوباره باز کنید.'
});
