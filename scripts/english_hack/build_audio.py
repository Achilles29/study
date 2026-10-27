"""Render original unit texts locally; no user data or remote synthesis service."""
from pathlib import Path
import json,hashlib,wave,os,sys
os.environ.setdefault('OMP_NUM_THREADS','2')
from piper import PiperVoice,SynthesisConfig
ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'application/content/english_hack/v1/audio'
OUT.mkdir(exist_ok=True)
data=json.loads((OUT.parent/'curriculum.json').read_text())
voice=PiperVoice.load('/opt/projectpintar-english-voices/en_US-ljspeech-high.onnx')
manifest={}
for level in data['levels']:
 for unit in level['units']:
  text=unit['context'];dest=OUT/(unit['id']+'.wav')
  if not dest.exists() or '--force' in sys.argv:
   with wave.open(str(dest),'wb') as f:voice.synthesize_wav(text,f,syn_config=SynthesisConfig(length_scale=1.55 if level['id'] in ['foundation','a1'] else (1.35 if level['id'] in ['a2','b1'] else 1.25)))
  with wave.open(str(dest),'rb') as f:duration=round(f.getnframes()/f.getframerate(),2)
  manifest[unit['id']]={'file':dest.name,'duration':duration,'text_sha256':hashlib.sha256(text.encode()).hexdigest(),'bytes':dest.stat().st_size}
  print(unit['id'],duration,flush=True)
(OUT/'manifest.json').write_text(json.dumps(manifest,indent=2))
(OUT/'attribution.json').write_text(json.dumps({'engine':'Piper 1.8.0 (GPL-3.0)','engine_source':'https://github.com/OHF-Voice/piper1-gpl','voice':'en_US-ljspeech-high','model_card':'https://huggingface.co/rhasspy/piper-voices/blob/main/en/en_US/ljspeech/high/MODEL_CARD','dataset_license':'public domain, as stated by model card','content':'Original Project Pintar curriculum contexts','audio_kind':'synthetic; not a human teacher or a voice recording of a user'},indent=2))
