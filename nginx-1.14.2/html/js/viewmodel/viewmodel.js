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