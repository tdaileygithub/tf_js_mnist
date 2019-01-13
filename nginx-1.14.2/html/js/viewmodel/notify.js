
class Notify {
    constructor() { 
    }
    msg(title, text, type) {
        new PNotify({
            width: "500px",
            title: title,
            text: text,
            type: type,
            delay: 1000,
            stack: {"dir1": "down", "dir2": "right", "push": "top","firstpos1": 25, "firstpos2": 25}
          });  
    };    
    info(title, text) {
        this.msg(title,text, "info");
    };
    success(title, text) {
        this.msg(title,text, "success");
    };
    error(title, text) {
        this.msg(title,text, "error");
    };
    notice(title, text) {
       this.msg(title,text, "notice");
    };
}