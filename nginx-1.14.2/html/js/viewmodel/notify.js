
class Notify {
    constructor() { 
        this.stack = {dir1: "up", dir2: "left"}; 
    }
    msg(title, text, type) {        
        new PNotify({
            width: "500px",
            title: title,
            text: text,
            type: type,
            delay: 1000,
            addclass: 'stack-bottomleft',
            stack:  this.stack
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