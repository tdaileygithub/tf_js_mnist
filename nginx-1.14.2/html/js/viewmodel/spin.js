
class Spin {
    constructor() { 
        var target = document.getElementById('spinner');
        var spinner = new Spinner(this.get_spinner_opts()).spin(target);       
        this.spin_stop();
    }
    get_spinner_opts() {
        return {
            lines: 13, // The number of lines to draw
            length: 38, // The length of each line
            width: 17, // The line thickness
            radius: 45, // The radius of the inner circle
            scale: 0.2, // Scales overall size of the spinner
            corners: 1, // Corner roundness (0..1)
            color: 'gray', // CSS color or array of colors
            fadeColor: 'transparent', // CSS color or array of colors
            speed: 1, // Rounds per second
            rotate: 0, // The rotation offset
            animation: 'spinner-line-fade-quick', // The CSS animation name for the lines
            direction: 1, // 1: clockwise, -1: counterclockwise
            zIndex: 2e9, // The z-index (defaults to 2000000000)
            className: 'spinner', // The CSS class to assign to the spinner
            top: '5%', // Top position relative to parent
            left: '50%', // Left position relative to parent
            shadow: '0 0 1px transparent', // Box-shadow for the lines
            position: 'absolute' // Element positioning
        };
    }
    spin_start() {
        $('#spinner').show();
    };
    spin_stop() {
        $('#spinner').hide();
    };
}