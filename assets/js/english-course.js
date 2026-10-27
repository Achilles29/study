(() => {
 'use strict';
 document.querySelectorAll('[data-word-count]').forEach(input=>{
  const target=document.getElementById(input.dataset.wordCount);
  const update=()=>{if(target)target.textContent=`${input.value.trim()?input.value.trim().split(/\s+/u).length:0} kata · Draf belum dikirim.`;};
  input.addEventListener('input',update);update();
 });
 let dirty=false;
 document.querySelectorAll('.ec-answer-form').forEach(form=>{
  form.addEventListener('input',()=>{dirty=true;});
  form.addEventListener('submit',()=>{dirty=false;});
 });
 window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
 document.querySelectorAll('audio').forEach(player=>player.addEventListener('play',()=>{document.querySelectorAll('audio').forEach(other=>{if(other!==player)other.pause();});}));
})();
