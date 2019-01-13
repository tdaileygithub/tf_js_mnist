<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- tenorflow.js -->
    <script src="/js/tf.min.js"></script>
    <!-- jquery -->
    <script src="/js/jquery.min.js"></script>    
    <!-- knockout -->
    <script src="/js/knockout-min.js"></script>
    <script src="/js/knockout.mapping.min.js"></script>
    <!-- processing.js -->
    <script src="/js/processing.min.js"></script>
    <script src="/js/p5.min.js"></script>
    <script src="/js/p5.dom.min.js"></script>
    <!-- amplify.js -->
    <script src="/js/amplify.min.js"></script>
    <script src="/js/amplify.request.min.js"></script>
    <script src="/js/amplify.store.min.js"></script>
    <!-- dexie.js -->
    <script src="/js/dexie.min.js"></script>
    <!-- boostrap -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">    
    <script src="/js/bootstrap.min.js" ></script>    
    <!-- spin.js -->
    <link rel="stylesheet" href="/css/spin.css" />    
    <script src="/js/spin.js"></script>
    <!-- c3 and d3 -->
    <link rel="stylesheet" href="/css/c3.min.css" />    
    <script src="/js/d3.min.js"></script>
    <script src="/js/c3.min.js"></script>
    <!-- pnotify -->
    <link rel="stylesheet" href="/css/pnotify.css" />    
    <link rel="stylesheet" href="/css/pnotify.brighttheme.css" />        
    <script src="/js/pnotify.js"></script>    
    <!-- our source -->
    <script src="/js/tf_funcs.js"></script>
    <script src="/js/viewmodel/spin.js"></script>    
    <script src="/js/viewmodel/notify.js"></script>
    <script src="/js/model/local_db.js"></script>
    <link rel="stylesheet" href="/css/app.css" />
    <title>MNIST using Tensorflow.js</title>
    
</head>
<body id="vm">
    
    <div class="container-fluid" style="margin-top: 4em">
        <div class="row" style="margin-top: 1em">
            <div class="col-1">
            <canvas id="canvas3"></canvas>
            <!-- <img src='image.php?imgid=12345' alt='this is your img from the database' /> -->                
            </div>
            <div class="col-7 ml-auto d-flex align-items-center button-row" align="center">
                <button type="button"                               class="btn btn-primary" data-bind="click: $root.load_data,                                    enable: load_data_button_enabled()">1) Load Data</button>
                <select class="custom-select" style="width: 10em" data-bind="options: model_types, value: selected_model_type,      enable: create_tf_model_button_enabled()"></select>
                <button type="button"                               class="btn btn-primary" data-bind="click: $root.create_tf_model,                              enable: create_tf_model_button_enabled()">2) Create TF Model</button>                
                <button type="button"                               class="btn btn-primary" data-bind="click: $root.train_model,                                  enable: train_button_enabled()">3) Train</button>
                <button type="button"                               class="btn btn-primary" data-bind="click: $root.save_model,                                   enable: save_model_button_enabled()">4) Save Model</button>
                <button type="button"                               class="btn btn-primary" data-bind="click: $root.predict,                                      enable: save_model_button_enabled()">5) Predict</button>
            </div>
            <div class="col-3">
                <table class="table">
                    <tr>
                        <td colspan="2"><div class="spinner"> <div id="spinner"></div> </div></td>                        
                    </tr>                    
                    <tr>
                        <td>Done %</td>
                        <td data-bind="text: percent_training_complete"></td>
                    </tr>
                    <tr>
                        <td>Accuracy %</td>
                        <td data-bind="text: current_accuracy"></td>
                    </tr>
                    <tr>
                        <td>Val Accuracy %</td>
                        <td data-bind="text: current_validation_accuracy"></td>
                    </tr>
                    <tr>
                        <td>Loss</td>
                        <td data-bind="text: current_loss"></td>
                    </tr>
                    <tr>
                        <td>Batch #</td>
                        <td data-bind="text: current_batch_num"></td>
                    </tr>
                    <tr>
                        <td>Epoch #</td>
                        <td data-bind="text: current_epoch"></td>
                    </tr>
                </table>                
            </div>         
            <div class="col-1">
            </div>                           
        </div>
        <div class="row" style="height:300px" data-bind="visible: is_training">>
            <div class="col-2">
                <h1>Training</h1>
            </div>
            <div class="col-4">
                <div id="accuracy_chart">
                </div>
            </div>
            <div class="col-4">
                <div id="loss_chart">
                </div>
            </div>
            <div class="col-2">
            </div>
        </div>          
        <div class="row" data-bind="visible: is_training">
            <div class="col-2 ml-auto d-flex align-items-center">
                <h1>Test Predictions</h1>
            </div>
            <div class="col-8">
                <div id="images" style="display:inline-flex"></div>
            </div>
            <div class="col-2">
            </div>            
        </div>            
    </div>
    <script>

const IMAGE_H = 28;
const IMAGE_W = 28;
const IMAGE_SIZE = IMAGE_H * IMAGE_W;
const NUM_CLASSES = 10;
const NUM_DATASET_ELEMENTS = 35700 + 6300;
const NUM_TRAIN_ELEMENTS = 35700 ;

// 55/65 = .15
// const NUM_DATASET_ELEMENTS = 65000;
// const NUM_TRAIN_ELEMENTS = 55000;
// const NUM_TEST_ELEMENTS = NUM_DATASET_ELEMENTS - NUM_TRAIN_ELEMENTS;

const NUM_TEST_ELEMENTS = NUM_DATASET_ELEMENTS - NUM_TRAIN_ELEMENTS;

    var ViewModel = function () {
        var self = this;        
        self.tf_model                       = ko.observable({});
        self.db                             = ko.observable(new LocalDb());
        self.spinner                        = ko.observable(new Spin());
        self.notify                         = ko.observable(new Notify());
        self.data_loaded                    = ko.observable(false);
        self.data_is_loading                = ko.observable(false);
        self.model_created                  = ko.observable(false);
        self.is_training                    = ko.observable(false);
        self.fetching                       = ko.observable(false);
        self.loss_chart                     = ko.observable({});
        self.accuracy_chart                 = ko.observable({});            
        self.loss_values                    = ko.observableArray(['loss']);
        self.accuracy_values                = ko.observableArray(['accuracy']);
        self.validation_loss_values         = ko.observableArray(['val_loss']);
        self.validation_accuracy_values     = ko.observableArray(['val_accuracy']);        
        self.percent_training_complete      = ko.observable(0);
        self.current_epoch                  = ko.observable(0);
        self.current_batch_num              = ko.observable(0);

        self.selected_model_type            = ko.observable('ConvNet');
        self.model_types                    = ko.observableArray(['ConvNet', 'DenseNet']);

        //WORKING
        self.train_images_raw   = new Float32Array(NUM_DATASET_ELEMENTS * (IMAGE_SIZE));
        self.train_labels_raw   = new Uint8Array(NUM_DATASET_ELEMENTS * (NUM_CLASSES));        

        self.tf_train_images = ko.pureComputed(function () {
            var ti = self.train_images_raw.slice(0, IMAGE_SIZE * NUM_TRAIN_ELEMENTS);
            return tf.tensor4d(ti, [ti.length / IMAGE_SIZE, IMAGE_H, IMAGE_W, 1]);
        }, self);

        self.tf_test_images = ko.pureComputed(function () {
            var ti = self.train_images_raw.slice(IMAGE_SIZE * NUM_TRAIN_ELEMENTS);
            return tf.tensor4d(ti, [ti.length / IMAGE_SIZE, IMAGE_H, IMAGE_W, 1]);
        }, self);

        self.tf_train_label = ko.pureComputed(function () {
            var tl = self.train_labels_raw.slice(0, NUM_CLASSES * NUM_TRAIN_ELEMENTS);
            return tf.tensor2d(tl, [tl.length / NUM_CLASSES, NUM_CLASSES]);
        }, self);
        self.tf_test_label = ko.pureComputed(function () {
            var tl = self.train_labels_raw.slice(NUM_CLASSES * NUM_TRAIN_ELEMENTS);
            return tf.tensor2d(tl, [tl.length / NUM_CLASSES, NUM_CLASSES]);
        }, self);   

        self.load_data_button_enabled = ko.pureComputed(function () {
            return  !self.data_loaded() &&
                    !self.data_is_loading() && 
                    !self.model_created() &&
                    !self.is_training();
        }, self);
        self.create_tf_model_button_enabled = ko.pureComputed(function () {
            return  self.data_loaded() && 
                    !self.model_created() &&
                    !self.is_training();
        }, self);
        self.train_button_enabled = ko.pureComputed(function () {
            return  self.data_loaded() && 
                    self.model_created() &&
                    !self.is_training();
        }, self);

        self.save_model_button_enabled = ko.pureComputed(function () {
            return true;
            // return  self.data_loaded() && 
            //         self.model_created() &&
            //         !self.is_training();
        }, self);

        self.current_accuracy = ko.pureComputed(function () {
            return self.accuracy_values()[self.accuracy_values().length-1];
        }, self);        
        self.current_validation_accuracy = ko.pureComputed(function () {
            return self.validation_accuracy_values()[self.validation_accuracy_values().length-1];
        }, self);                
        self.current_loss = ko.pureComputed(function () {
            return self.loss_values()[self.loss_values().length-1];
        }, self);        

        self.getTrainData = function () {
            return  self.mnist_data().getTrainData();
        };

        self.getTestData = function (numExamples) {
            return  self.mnist_data().getTestData(numExamples);
        };


        //working
        self.getTrainData = ko.pureComputed(function () {
            const xs = self.tf_train_images();
            const labels = self.tf_train_label();
            return  {xs, labels};
        }, self);

        self.getTestData = function (numExamples) {
            let xs      = self.tf_test_images();
            let labels  = self.tf_test_label();
            if (numExamples != null) {
                xs = xs.slice([0, 0, 0, 0], [numExamples, IMAGE_H, IMAGE_W, 1]);
                labels = labels.slice([0, 0], [numExamples, NUM_CLASSES]);
            }
            return  {xs, labels};
        };

        self.getTrainData = ko.pureComputed(function () {
            const xs = self.tf_train_images();
            const labels = self.tf_train_label();
            return  {xs, labels};
        }, self);

        self.getTestData = function (numExamples) {
            let xs      = self.tf_test_images();
            let labels  = self.tf_test_label();
            if (numExamples != null) {
                xs = xs.slice([0, 0, 0, 0], [numExamples, IMAGE_H, IMAGE_W, 1]);
                labels = labels.slice([0, 0], [numExamples, NUM_CLASSES]);
            }
            return  {xs, labels};
        };        

        self.get_model = function() {
            let model;
            if (self.selected_model_type() === 'ConvNet') {
                model = createConvModel();
            } else if (self.selected_model_type() === 'DenseNet') {
              model = createDenseModel();
            } else {
              throw new Error(`Invalid model type: ${modelType}`);
            }
            return model;
        };

        self.create_tf_model = function () {
            self.notify().info('','Creating Tensorflow Model' );
            self.tf_model(self.get_model());
            self.tf_model().summary();
            self.model_created(true);
        };

        self.batch_end = function (batch_num, totalNumBatches, logs) {
            //console.info('batch end',batch_num, totalNumBatches, logs);

            self.current_batch_num(batch_num);
            self.loss_values.push(logs.loss.toFixed(2));
            self.accuracy_values.push((100.0 * logs.acc).toFixed(1));
            self.percent_training_complete((batch_num / totalNumBatches * 100).toFixed(1));

            if (0 == batch_num % 10) {
                self.loss_chart().load({
                    columns: [
                        self.loss_values()
                    ]                
                });
                self.accuracy_chart().load({
                    columns: [
                        self.accuracy_values()
                    ]                
                });
            }
        };

        self.epoch_end = function (epoch, batch_num, totalNumBatches, logs) {            
            console.info('epoch_end',epoch, batch_num, totalNumBatches,logs);
            self.current_epoch(epoch);
            self.validation_loss_values.push(logs.val_loss.toFixed(2));
            self.validation_accuracy_values.push((100.0 * logs.val_acc).toFixed(1));
        };        

        self.train_model = function () {
            self.is_training(true);

            self.loss_chart(c3.generate({
                bindto: '#loss_chart',
                size: {
                    height: 240,
                    width: 480
                },                                
                data: {
                    columns: [
                        ['loss'],
                    ]
                }
            }));            
            self.accuracy_chart(c3.generate({
                bindto: '#accuracy_chart',
                size: {
                    height: 240,
                    width: 480
                },                
                data: {
                    columns: [
                        ['accuracy'],
                    ]
                }
            }));

            train(  self.tf_model(), 
                    (batch_num, total_num_batches, logs)        => self.batch_end(batch_num, total_num_batches, logs),
                    (epoch, batch_num, total_num_batches, logs) => self.epoch_end(epoch, batch_num, total_num_batches, logs),
                    (event, batch, logs)                        => showPredictions(self.tf_model(), event, batch, logs));
        };        

        self.save_model = async function() {
            await self.tf_model().save(
                tf.io.browserHTTPRequest(
                '/save_model.php',
                {   
                    method: 'PUT', 
                    headers: {'header_key_1': 'header_value_1'}
                })
                );
        };

        self.predict = async function () {

        };

        self.save_to_db = async function () {            
            self.notify().notice('','Saving Data to IndexedDB' );
            
            await self.db().save_data(
                self.train_images_raw, 
                self.train_labels_raw,
                NUM_DATASET_ELEMENTS,
                IMAGE_SIZE,
                NUM_CLASSES
            );
            //HACK
            amplify.store( 'data_loaded',       true);
            self.notify().info('','Data Saved to IndexedDB');            
        };

        self.load_data = async function (){
            self.data_is_loading(true);

            if (amplify.store( 'data_loaded')) {                
                self.notify().info('','Loading from IndexdDb');

                const d = await self.db().get_data(
                    NUM_DATASET_ELEMENTS,
                    IMAGE_SIZE,
                    NUM_CLASSES
                );
                self.train_images_raw = d.train_images_raw;
                self.train_labels_raw = d.train_labels_raw;
                
                self.data_is_loading(false);
                self.data_loaded(true);                       
            }
            else {                
                self.notify().notice('','Loading from AJAX');

                $.ajax(
                {
                    url:        '/api.php',					  
                    dataType:   'json',
                }).done(function(data) {
                    self.notify().notice('','Data Loaded - Converting PNG to Pixels...');

                    var canvas = document.getElementById('canvas3');
                    canvas.width  = 28;
                    canvas.height = 28;                            
                    var ctx = canvas.getContext('2d');               
                    var myImageData = ctx.createImageData(28, 28);

                    var row_num=0;
                    alert(data.train.length);
                    data.train.forEach( function (trainobj) {
                        var image_label = trainobj.label;                    
                        
                        var img = new Image();
                        img.id=trainobj.id;
                        img.row_num=row_num;
                        img.onload = function() {

                            var pixels		= [];                            
                            ctx.drawImage(img, 0, 0);                            
                            var pix = ctx.getImageData(0, 0, 28, 28).data;
                            for (var i = 0, n = pix.length; i < n; i += 4) {
                                pixels.push(pix[i])
                            }
                            const image_base_offset = this.row_num * IMAGE_SIZE;
                            const label_base_offset = this.row_num * NUM_CLASSES;								
                            var index = 0;
                            for (var row = 0; row < IMAGE_H; row++) {
                                for (var col = 0; col < IMAGE_W; col++, index++) {										
                                    self.train_images_raw[image_base_offset + index] = pixels[index];
                                }
                            }      
                            for (var lo=0; lo<10; lo++){                                
                                self.train_labels_raw[label_base_offset+lo] = (image_label === lo) ? 1 : 0;
                            }                            
                            img=null;                            
                            if (this.row_num==42000-1) {
                                self.notify().info('','Pixel Conversion Complete');
                                //alert('set data is load - save to local db');
                                self.data_is_loading(false);
                                self.data_loaded(true);                                
                            }
                        };
                        img.src = 'data:image/png;base64,' + trainobj.pixels;
                        row_num++;
                    });                
                }).fail(function(jqXHR, textStatus, errorThrown) {                                        
                    self.notify().error('',textStatus + ': ' + errorThrown);
                });
            }
        };

        self.subscribe = function (){
            self.data_is_loading.subscribe(function(newValue) {
                if (newValue) {
                    self.spinner().spin_start();
                }
                else {
                    self.spinner().spin_stop();
                }
            });
            self.is_training.subscribe(function(newValue) {
                if (newValue) {
                    self.spinner().spin_start();
                }
                else {
                    self.spinner().spin_stop();
                }
            });   
            self.data_loaded.subscribe(function(newValue) {
                if (newValue) {                    
                    self.db().get_images_count().done(function(img_count){
                        if (img_count > 0)
                        {
                            self.notify().info('','Loaded IndexedDB: ' + img_count + ' images' );
                        }
                        else{                            
                            self.save_to_db();
                        }
                    });
                }
                else {
                    //data_loaded = false                    
                }
            });   
        };        
        
        self.subscribe();
    };

    //Dexie.delete('localmnist');
    //amplify.store( 'data_loaded',null)

    //var img;
    var vm = new ViewModel();
    ko.applyBindings(vm, document.getElementById('vm'));
    //vm.load_data();


const imagesElement = document.getElementById('images');

function draw2(image, canvas) {
    if (canvas) {
        const [width, height] = [28, 28];
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        const imageData = new ImageData(width, height);
        const data = image.dataSync();
        for (let i = 0; i < height * width; ++i) {
            const j = i * 4;
            imageData.data[j + 0] = data[i] * 255;
            imageData.data[j + 1] = data[i] * 255;
            imageData.data[j + 2] = data[i] * 255;
            imageData.data[j + 3] = 255;
        }
        ctx.putImageData(imageData, 0, 0);
    }
}

function showTestResults(batch, predictions, labels) {
  const testExamples = batch.xs.shape[0];
  imagesElement.innerHTML = '';
  for (let i = 0; i < testExamples; i++) {
    const image = batch.xs.slice([i, 0], [1, batch.xs.shape[1]]);

    const div = document.createElement('div');
    div.className = 'pred-container';

    const canvas = document.createElement('canvas');
    canvas.className = 'prediction-canvas';
    draw2(image.flatten(), canvas);

    const pred = document.createElement('div');

    const prediction = predictions[i];
    const label = labels[i];
    const correct = prediction === label;

    pred.className = `pred ${(correct ? 'pred-correct' : 'pred-incorrect')}`;
    pred.innerText = `pred: ${prediction}`;

    div.appendChild(pred);
    div.appendChild(canvas);

    imagesElement.appendChild(div);
  }
}

/**
 * Show predictions on a number of test examples.
 *
 * @param {tf.Model} model The model to be used for making the predictions.
 */
async function showPredictions(model,event, batch, logs) {
  console.info('showPredictions', event, batch, logs);

  const testExamples = 10;  
  const examples = vm.getTestData(testExamples);

  // Code wrapped in a tf.tidy() function callback will have their tensors freed
  // from GPU memory after execution without having to call dispose().
  // The tf.tidy callback runs synchronously.
  tf.tidy(() => {
    const output = model.predict(examples.xs);

    // tf.argMax() returns the indices of the maximum values in the tensor along
    // a specific axis. Categorical classification tasks like this one often
    // represent classes as one-hot vectors. One-hot vectors are 1D vectors with
    // one element for each output class. All values in the vector are 0
    // except for one, which has a value of 1 (e.g. [0, 0, 0, 1, 0]). The
    // output from model.predict() will be a probability distribution, so we use
    // argMax to get the index of the vector element that has the highest
    // probability. This is our prediction.
    // (e.g. argmax([0.07, 0.1, 0.03, 0.75, 0.05]) == 3)
    // dataSync() synchronously downloads the tf.tensor values from the GPU so
    // that we can use them in our normal CPU JavaScript code
    // (for a non-blocking version of this function, use data()).
    const axis = 1;
    const labels = Array.from(examples.labels.argMax(axis).dataSync());
    const predictions = Array.from(output.argMax(axis).dataSync());
    //console.info(examples, predictions, labels);
    //ui.showTestResults(examples, predictions, labels);

      showTestResults(examples, predictions, labels);
  });
}
//TODO:   processing.js functions
$(document).ready(function() {
});

    </script>
</body>
</html>