'use strict';
document.querySelectorAll('[data-course-grade-picker]').forEach(container => {
 const select=container.querySelector('select'), rows=[...container.querySelectorAll('[data-course-grade]')], status=container.querySelector('[role="status"]');
 const update=()=>{let count=0;for(const row of rows){const selected=row.querySelector('input[type="checkbox"]').checked;row.hidden=!!select.value && row.dataset.courseGrade!==select.value && !selected;if(!row.hidden)count++;}status.textContent=count+' kursus ditampilkan. Kursus yang sudah dicentang tetap terlihat dan tidak dilepas oleh filter.';};
 select.disabled=false;select.addEventListener('change',update);container.addEventListener('change',event=>{if(event.target.type==='checkbox')update();});update();
});
