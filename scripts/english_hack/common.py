"""Original editorial source for Project Pintar's English course concept."""
def Q(prompt, answer, distractors, why, skill="grammar"):
    return {"type":"choice","prompt":prompt,"answer":answer,"distractors":distractors.split("||"),"explanation":why,"skill":skill}
def S(prompt, answers, why, skill="grammar"):
    return {"type":"short","prompt":prompt,"answers":answers.split("||"),"explanation":why,"skill":skill}
def T(skill, prompt, model, criteria):
    return {"skill":skill,"prompt":prompt,"model":model,"criteria":criteria.split("|")}
def U(code,title,goal,focus,lesson,patterns,error,context,words,questions,tasks,chart="sentence",depends=()):
    return {"id":code,"title":title,"goal":goal,"focus":focus.split("|"),"lesson":lesson.split("||"),
            "patterns":[dict(zip(("form","example","meaning"),x)) for x in patterns],
            "common_error":{"wrong":error[0],"correct":error[1],"reason":error[2]},
            "context":context,"vocabulary":[{"term":x.split("=",1)[0],"meaning":x.split("=",1)[1]} for x in words.split("|")],
            "questions":questions,"tasks":tasks,"chart":chart,"prerequisites":list(depends)}
