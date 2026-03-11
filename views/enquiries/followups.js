<script>
/* =========================================================
   1) MODAL FUNCTIONS
========================================================= */
function openCrmModal(title){
  document.getElementById('crmModalTitle').innerText = title || 'Details';
  document.getElementById('crmModalBody').innerHTML = '<div class="muted">Loading...</div>';
  document.getElementById('crmModalBackdrop').style.display = 'flex';
}
function closeCrmModal(){
  document.getElementById('crmModalBackdrop').style.display = 'none';
  document.getElementById('crmModalBody').innerHTML = '';
}
document.getElementById('crmModalBackdrop').addEventListener('click', function(e){
  if(e.target === this) closeCrmModal();
});
async function loadModalHtml(url){
  const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  return await res.text();
}
async function openHistoryModal(enquiryId){
  openCrmModal('Enquiry History');
  const url = `index.php?page=enquiries/followups&ajax=1&action=enquiry_history&enquiry_id=${enquiryId}`;
  const html = await loadModalHtml(url);
  document.getElementById('crmModalBody').innerHTML = html;

  document.querySelectorAll('#crmModalBody .verifyForm').forEach(f=>{
    f.addEventListener('submit', function(e){
      e.preventDefault();
      Swal.fire({
        icon:'warning',
        title:'Confirm Verification?',
        text:'Approve/Reject this verification.',
        showCancelButton:true,
        confirmButtonText:'Confirm',
        cancelButtonText:'Cancel',
        confirmButtonColor:'#e91e63'
      }).then((r)=>{ if(r.isConfirmed) f.submit(); });
    });
  });
}
async function openEditModal(followupId){
  openCrmModal('Edit Follow-up');
  const url = `index.php?page=enquiries/followups&ajax=1&action=edit_followup&id=${followupId}`;
  const html = await loadModalHtml(url);
  document.getElementById('crmModalBody').innerHTML = html;
}

/* =========================================================
   2) EDIT MODAL: FILE LIST UI
========================================================= */
function mfShowFiles(inp){
  const el = document.getElementById('mfFilesList');
  if (!el) return;

  const files = inp.files ? Array.from(inp.files) : [];
  if (files.length === 0){
    el.textContent = 'No files selected';
    return;
  }

  el.innerHTML = files.map(f => {
    const kb = Math.round((f.size || 0) / 1024);
    return `<span class="mf-filechip"><i class="fas fa-paperclip"></i> ${f.name} <em>${kb} KB</em></span>`;
  }).join(' ');
}

/* =========================================================
   3) DONE + CONVERT (FIXED UI BUG)
========================================================= */
document.querySelectorAll('.doneForm').forEach(f => {
  f.addEventListener('submit', function(e){
    e.preventDefault();

    Swal.fire({
      icon:'question',
      title:'Mark as Done?',
      html: `<div style="text-align:left;font-size:13px;color:#666;margin-bottom:10px;">
              If you mark as done, this enquiry can be converted to Registration.
            </div>`,
      showDenyButton: true,
      showCancelButton: true,
      confirmButtonText: 'Done + Convert',
      denyButtonText: 'Only Done',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#e91e63'
    }).then((r)=>{
      if (r.isConfirmed) {
        Swal.fire({
          icon: 'info',
          title: 'Choose Registration Type',
          html: `
            <div class="swal-select-wrap">
              <select id="regTypeSelect" class="swal-modern-select">
                <option value="">Select type</option>
                <option value="course">Course</option>
                <option value="internship">Internship</option>
                <option value="workshop">Workshop</option>
              </select>
            </div>
          `,
          showCancelButton: true,
          confirmButtonText: 'Convert',
          confirmButtonColor: '#e91e63',
          focusConfirm: false,
          preConfirm: () => {
            const value = document.getElementById('regTypeSelect').value;
            if (!value) {
              Swal.showValidationMessage('Please choose a type');
              return false;
            }
            return value;
          }
        }).then((x)=>{
          if (!x.isConfirmed) return;

          let c = f.querySelector('input[name="convert"]');
          if (!c) { c = document.createElement('input'); c.type='hidden'; c.name='convert'; f.appendChild(c); }
          c.value = '1';

          let t = f.querySelector('input[name="reg_type"]');
          if (!t) { t = document.createElement('input'); t.type='hidden'; t.name='reg_type'; f.appendChild(t); }
          t.value = x.value;

          f.submit();
        });

      } else if (r.isDenied) {
        let c = f.querySelector('input[name="convert"]');
        if (!c) { c = document.createElement('input'); c.type='hidden'; c.name='convert'; f.appendChild(c); }
        c.value = '0';
        f.submit();
      }
    });
  });
});

/* =========================================================
   4) ADD TAB: VALIDATION + REMINDER + UPLOAD PREVIEW
========================================================= */
(function(){
  const addForm = document.getElementById('addFollowupForm');
  if (!addForm) return;

  const enquirySel = addForm.querySelector('select[name="enquiry_id"]');
  const fDate = addForm.querySelector('input[name="followup_date"]');
  const fTime = addForm.querySelector('input[name="followup_time"]');
  const fType = addForm.querySelector('select[name="followup_type"]');
  const nextDate = addForm.querySelector('input[name="next_followup_date"]');
  const nextTime = addForm.querySelector('input[name="next_followup_time"]');
  const notes = addForm.querySelector('textarea[name="notes"]');

  const filesInp = addForm.querySelector('#addAttachments');
  const preview = document.getElementById('addFilePreview');

  const banner = document.getElementById('scheduleBanner');
  const bannerText = document.getElementById('scheduleBannerText');

  function toYMD(d){
    const z = n => String(n).padStart(2,'0');
    return `${d.getFullYear()}-${z(d.getMonth()+1)}-${z(d.getDate())}`;
  }
  function toPretty(ymd){
    const parts = (ymd || '').split('-');
    if (parts.length !== 3) return ymd;
    return `${parts[2]}-${parts[1]}-${parts[0]}`;
  }

  window.hideScheduleBanner = function(){
    if (banner) banner.style.display = 'none';
  }

  function showBanner(msg){
    if (!banner || !bannerText) return;
    bannerText.innerText = msg;
    banner.style.display = 'flex';
  }

  function updateBannerByDate(){
    const chosen = (fDate?.value || '').trim();
    if (!chosen) { if (banner) banner.style.display='none'; return; }

    const today = toYMD(new Date());

    if (chosen === today){
      showBanner(`Follow-up is set for Today (${toPretty(chosen)}).`);
      return;
    }
    if (chosen > today){
      const t = (fTime?.value || '').trim();
      showBanner(`Follow-up scheduled on ${toPretty(chosen)}${t ? (' at ' + t) : ''}.`);
      return;
    }
    showBanner(`⚠ This follow-up date is in the past (${toPretty(chosen)}). Please confirm.`);
  }

  function fileIcon(name){
    const ext = (name.split('.').pop() || '').toLowerCase();
    if (['jpg','jpeg','png','webp','gif'].includes(ext)) return 'fa-image';
    if (['mp3','wav','m4a','aac','ogg'].includes(ext)) return 'fa-headphones';
    if (['mp4','mov','avi','mkv','webm'].includes(ext)) return 'fa-video';
    if (['pdf','doc','docx','xls','xlsx','ppt','pptx','txt'].includes(ext)) return 'fa-file-alt';
    return 'fa-paperclip';
  }

  if (filesInp && preview){
    filesInp.addEventListener('change', function(){
      preview.innerHTML = '';
      const files = Array.from(this.files || []);
      if (!files.length) return;

      files.slice(0,12).forEach(f=>{
        const chip = document.createElement('div');
        chip.className = 'file-chip';
        chip.innerHTML = `<i class="fas ${fileIcon(f.name)}"></i> ${f.name}`;
        preview.appendChild(chip);
      });

      if (files.length > 12){
        const chip = document.createElement('div');
        chip.className = 'file-chip';
        chip.innerHTML = `<i class="fas fa-ellipsis-h"></i> +${files.length-12} more`;
        preview.appendChild(chip);
      }
    });
  }

  fDate && fDate.addEventListener('change', function(){
    updateBannerByDate();
    const chosen = (this.value || '').trim();
    if (!chosen) return;

    Swal.fire({
      icon: 'info',
      title: 'Reminder',
      text: `Follow-up scheduled on ${toPretty(chosen)}.`,
      confirmButtonColor: '#e91e63'
    });
  });
  fTime && fTime.addEventListener('change', updateBannerByDate);

  function validateAddFollowup(){
    const enq = (enquirySel?.value || '').trim();
    const fd  = (fDate?.value || '').trim();
    const ft  = (fType?.value || 'call').trim();
    const nd  = (nextDate?.value || '').trim();
    const ntt = (nextTime?.value || '').trim();
    const nt  = (notes?.value || '').trim();

    if (!enq){
      Swal.fire({icon:'error',title:'Required',text:'Please select an enquiry.',confirmButtonColor:'#e91e63'});
      return false;
    }
    if (!fd){
      Swal.fire({icon:'error',title:'Required',text:'Follow-up date is required.',confirmButtonColor:'#e91e63'});
      return false;
    }
    if (nd && nd < fd){
      Swal.fire({icon:'error',title:'Invalid Next Date',text:'Next follow-up date cannot be before follow-up date.',confirmButtonColor:'#e91e63'});
      return false;
    }
    if (!nd && ntt){
      Swal.fire({icon:'error',title:'Next Date Missing',text:'You selected next follow-up time, please select next follow-up date also.',confirmButtonColor:'#e91e63'});
      return false;
    }
    if (['call','whatsapp','sms','email','walkin','other'].includes(ft) && nt.length < 3){
      Swal.fire({icon:'error',title:'Notes Required',text:'Please add short notes (minimum 3 characters).',confirmButtonColor:'#e91e63'});
      return false;
    }
    return true;
  }

  addForm.addEventListener('submit', function(e){
    if (!validateAddFollowup()){
      e.preventDefault();
      return;
    }
    e.preventDefault();
    Swal.fire({
      icon:'question',
      title:'Save Follow-up?',
      text:'Do you want to create this follow-up?',
      showCancelButton:true,
      confirmButtonText:'Yes, Save',
      cancelButtonText:'Cancel',
      confirmButtonColor:'#e91e63'
    }).then(r=>{
      if (r.isConfirmed) addForm.submit();
    });
  });

  updateBannerByDate();
})();
</script>