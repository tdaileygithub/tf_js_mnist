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
    self.train_images_raw       = new Float32Array(NUM_DATASET_ELEMENTS * (IMAGE_SIZE));
    self.train_labels_raw       = new Uint8Array(NUM_DATASET_ELEMENTS * (NUM_CLASSES)); 
    self.predict_images_raw     = new Float32Array(NUM_PREDICT_ELEMENTS * (IMAGE_SIZE));

    self.predict_offset_to_db_id    = new Uint32Array(NUM_PREDICT_ELEMENTS);
    self.train_offset_to_db_id      = new Uint32Array(NUM_DATASET_ELEMENTS);

    self.number_training_images = ko.pureComputed(function () {
        return parseInt(self.train_images_raw.length / IMAGE_SIZE,10);        
    }, self);

    self.number_predict_images = ko.pureComputed(function () {
        return parseInt(self.predict_images_raw.length / IMAGE_SIZE,10);        
    }, self);
    
    self.tf_train_images = ko.pureComputed(function () {
        var ti = self.train_images_raw.slice(0, IMAGE_SIZE * NUM_TRAIN_ELEMENTS);
        return tf.tensor4d(ti, [ti.length / IMAGE_SIZE, IMAGE_H, IMAGE_W, 1]);
    }, self);
    
    self.tf_predict_images = ko.pureComputed(function () {
        var ti = self.predict_images_raw.slice(0);
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
                !self.data_is_loading() &&
                !self.model_created() &&
                !self.is_training();
    }, self);
    self.train_button_enabled = ko.pureComputed(function () {
        return  self.data_loaded() && 
                self.model_created() &&
                !self.is_training();
    }, self);

    self.predict_button_enabled = ko.pureComputed(function () {        
        return  self.data_loaded() && 
                self.model_created();        
    }, self);    

    self.load_model_button_enabled = ko.pureComputed(function () {        
        return  self.data_loaded();
    }, self);

    self.save_model_button_enabled = ko.pureComputed(function () {        
        return  self.training_complete();
    }, self);

    self.training_complete = ko.pureComputed(function () {
        return self.percent_training_complete() >= 100 ;
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

    self.getTestData = function (numExamples) {
        let xs      = self.tf_test_images();
        let labels  = self.tf_test_label();
        if (numExamples != null) {
            xs = xs.slice([0, 0, 0, 0], [numExamples, IMAGE_H, IMAGE_W, 1]);
            labels = labels.slice([0, 0], [numExamples, NUM_CLASSES]);
        }
        return  {xs, labels};
    };

    self.getPredictData = function () {
        let xs      = self.tf_test_images();
        return  {xs};
    };    

    self.getTrainData = ko.pureComputed(function () {
        const xs        = self.tf_train_images();
        const labels    = self.tf_train_label();
        return  {xs, labels};
    }, self);

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
        self.current_batch_num(batch_num);
        self.loss_values.push(logs.loss.toFixed(2));
        self.accuracy_values.push((100.0 * logs.acc).toFixed(1));
        self.percent_training_complete((batch_num / totalNumBatches * 100).toFixed(1));
    };

    self.epoch_end = function (epoch, batch_num, totalNumBatches, logs) {            
        console.info('epoch_end',epoch, batch_num, totalNumBatches,logs);
        self.current_epoch(epoch);
        self.validation_loss_values.push(logs.val_loss.toFixed(2));
        self.validation_accuracy_values.push((100.0 * logs.val_acc).toFixed(1));
    };        

    self.train_model = function () {
        self.is_training(true);

        self.accuracy_chart(c3.generate({
            bindto: '#accuracy_chart',
            size: {
                height: 240,
                width: 480
            },                                
            data: {
                columns: [
                    ['loss', 'accuracy'],
                ],
                axes: {
                    data1: 'y',
                    data2: 'y2'
                }                
            },
            axis: {
                y2: {
                    show: true
                }
            }            
        }));            
        train(  self.tf_model(), 
                (batch_num, total_num_batches, logs)        => self.batch_end(batch_num, total_num_batches, logs),
                (epoch, batch_num, total_num_batches, logs) => self.epoch_end(epoch, batch_num, total_num_batches, logs),
                (event, batch, logs)                        => showPredictions(self.tf_model(), event, batch, logs));
    };        

    self.load_model = async function () {                
        const model = await tf.loadModel('indexeddb://mnistmodel');
        self.tf_model(model);
        self.notify().success('','Loaded Saved Model' );
        self.model_created(true);
    };

    self.save_model = async function() {
        alert('todo - save to file,sqlite, indexedb?');

        await self.tf_model().save(
            tf.io.browserHTTPRequest(
            '/save_model.php',
            {   
                method: 'PUT', 
                headers: {'header_key_1': 'header_value_1'}
            })
            );
    };

    self.predict = function () 
    {
        console.info('kaggle predictions');
        
        const predict_imgs = self.tf_predict_images();
    
        // Code wrapped in a tf.tidy() function callback will have their tensors freed
        // from GPU memory after execution without having to call dispose().
        // The tf.tidy callback runs synchronously.
        tf.tidy(() => {
            const output = self.tf_model().predict(predict_imgs);
    
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
            const predictions = Array.from(output.argMax(axis).dataSync());
            console.info(predictions);

            $.ajax(
                {
                    url:        '/predict.php',					  
                    dataType:   'json',
                }).done(function(data) {


                    const lookups = data.predict;   
                    const rows = [["ImageId","Label"]];
                    let csvContent = "data:text/csv;charset=utf-8,";

                    for (var i=0;i<predictions.length;i++){
                        const db_id = self.predict_offset_to_db_id[i];

                        const dbinfo = Enumerable.from(lookups)
                        .where(function (x) { return x.id === db_id })
                        .first();

                        rows.push([dbinfo.csv_row, predictions[i]]);
                    }


                    rows.forEach(function(rowArray){
                       let row = rowArray.join(",");
                       csvContent += row + "\r\n";
                    });             
                    var encodedUri = encodeURI(csvContent);
                    window.open(encodedUri);
                    
                    

                }).fail(function(jqXHR, textStatus, errorThrown) {                                        
                    self.notify().error(textStatus, errorThrown);
                });

            // ImageId,Label
            // 1,0
            // 2,0
            // 3,0


            
        });
    };

    self.save_to_db = function () {            
        self.notify().notice('','Saving Data to IndexedDB' );
        
        self.db().save_data(
            self.train_images_raw, 
            self.train_labels_raw,
            self.predict_images_raw, 
            self.predict_offset_to_db_id,
            self.train_offset_to_db_id,            
            NUM_DATASET_ELEMENTS,
            NUM_PREDICT_ELEMENTS,
            IMAGE_SIZE,
            NUM_CLASSES
        ).then (function() {
            self.notify().info('','Data Saved to IndexedDB');            
            amplify.store( 'data_loaded',       true);                      
        });
    };

    self.load_training_data = function(training_data) {
        var dfd = jQuery.Deferred();

        var canvas = document.getElementById('canvas3');
        canvas.width  = 28;
        canvas.height = 28;                            
        var ctx = canvas.getContext('2d');               
        var myImageData = ctx.createImageData(28, 28);

        var row_num=0;                
        training_data.forEach( function (trainobj) {
            var image_label = trainobj.label;                    
            
            var img = new Image();
            //img.id=trainobj.id;
            img.row_num=row_num;
            self.train_offset_to_db_id[row_num] = trainobj.id;
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
                    self.notify().info('','Training Conversion Complete');
                    //alert('set data is load - save to local db');
                    dfd.resolve( );
                }
            };
            img.src = 'data:image/png;base64,' + trainobj.pixels;
            row_num++;
        });    
        return dfd.promise();
    };

    self.load_predict_data = function(predict_data) {
        var dfd = jQuery.Deferred();

        var canvas = document.getElementById('canvas3');
        canvas.width  = 28;
        canvas.height = 28;                            
        var ctx = canvas.getContext('2d');               
        var myImageData = ctx.createImageData(28, 28);

        var row_num=0;        
        predict_data.forEach( function (predictobj) {
            var img = new Image();
            //img.id=predictobj.id;
            img.row_num=row_num;
            self.predict_offset_to_db_id[row_num] = predictobj.id;
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
                        self.predict_images_raw[image_base_offset + index] = pixels[index];
                    }
                }      
                img=null;                            
                if (this.row_num==28000-1) {
                    self.notify().info('','Predict Conversion Complete');
                    //alert('set data is load - save to local db');
                    dfd.resolve( );
                }
            };
            img.src = 'data:image/png;base64,' + predictobj.pixels;
            row_num++;
        });    
        return dfd.promise();
    };

    self.load_data = async function (){
        self.data_is_loading(true);

        if (amplify.store( 'data_loaded')) {                
            self.notify().info('','Loading from IndexdDb');

            const td = await self.db().get_training_data(
                NUM_DATASET_ELEMENTS,
                IMAGE_SIZE,
                NUM_CLASSES
            );
            self.train_images_raw = td.train_images_raw;
            self.train_labels_raw = td.train_labels_raw;
            self.notify().info('','Training Data Loaded');

            const tdo = await self.db().get_train_offset_map(
                NUM_DATASET_ELEMENTS                
            );
            self.train_offset_to_db_id = tdo.train_offset_map;            
            self.notify().info('','Training Offsets Loaded');

            const pd = await self.db().get_prediction_data(
                NUM_PREDICT_ELEMENTS,
                IMAGE_SIZE
            );
            self.predict_images_raw = pd.predict_images_raw;            
            self.notify().info('','Prediction Data Loaded');

            const pdo = await self.db().get_predict_offset_map(
                NUM_PREDICT_ELEMENTS                
            );
            self.predict_offset_to_db_id = pdo.predict_offset_map;            
            self.notify().info('','Prediction Offsets Loaded');            
            
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
                
                self.load_training_data(data.train).then(function() {
                    self.notify().info('','Training Conversion Complete');
                    self.load_predict_data(data.predict).then(function() {
                        self.notify().info('','Prediction Conversion Complete');
                        self.data_is_loading(false);
                        self.data_loaded(true);                                                            
                    });
                });

            }).fail(function(jqXHR, textStatus, errorThrown) {                                        
                self.notify().error(textStatus, errorThrown);
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
        self.percent_training_complete.subscribe(function(newValue) {
            if (newValue >= 100) {
                self.spinner().spin_stop();
                self.notify().success('','Training Complete !!!' );
                self.notify().success('','Validation Accuracy: ' + self.current_validation_accuracy() );
                tf.io.removeModel('indexeddb://mnistmodel');
                const saveResult = self.tf_model().save('indexeddb://mnistmodel');
                // List models in Local Storage.
                console.log(tf.io.listModels());   
                alert('saved');
            }
        });   
        self.current_batch_num.subscribe(function(newValue) {
            if (0 == newValue % 10) {
                self.accuracy_chart().load({
                    columns: [
                        self.accuracy_values(),
                        self.loss_values(),
                    ]                
                });
            }
        });
        
        self.data_loaded.subscribe(function(newValue) {
            if (newValue) {                    
                self.db().get_images_count().done(function(img_count){
                    if (img_count > 0)
                    {
                        //self.notify().info('','Loaded IndexedDB: ' + img_count + ' images' );
                    }
                    else{                            
                        self.notify().success('','Saving to database' );
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