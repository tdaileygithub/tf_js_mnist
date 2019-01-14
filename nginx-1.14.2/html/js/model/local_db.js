
class LocalDb {
    constructor() {                 
        this.db = new Dexie("localmnist");
        this.db.version(1).stores({
            images:         'id',
            predict_images: 'id'
        });        
    }

    save_train_data( 
        train_images_raw,
        train_labels_raw, 
        NUM_DATASET_ELEMENTS,
        IMAGE_SIZE,
        NUM_CLASSES)
    {
        var dfd = jQuery.Deferred();
        this.db.transaction('rw', this.db.images, async ()=>{
            for (var i=0; i< NUM_DATASET_ELEMENTS; i+=1) {                
                const image_offset=(i*IMAGE_SIZE);
                const label_offset=(i*NUM_CLASSES);
                this.db.images.add(
                {
                    id:          i,
                    train_image: train_images_raw.slice(image_offset, image_offset+IMAGE_SIZE), 
                    train_label: train_labels_raw.slice(label_offset, label_offset+NUM_CLASSES)
                });       
            }
        }).then(result => {                        
            dfd.resolve();            
        }).catch(function(error) {
            alert ("Ooops: " + error);
        });
        return dfd.promise();
    }           
    
    save_predict_data( 
        predict_images_raw,
        NUM_PREDICT_ELEMENTS,
        IMAGE_SIZE)
    {
        var dfd = jQuery.Deferred();
        this.db.transaction('rw', this.db.predict_images, async ()=>{
            for (var i=0; i< NUM_PREDICT_ELEMENTS; i+=1) {                
                const image_offset=(i*IMAGE_SIZE);                
                this.db.predict_images.add(
                {
                    id:             i,
                    predict_image:  predict_images_raw.slice(image_offset, image_offset+IMAGE_SIZE)                    
                });       
            }            
            dfd.resolve(  );
        });       
        return dfd.promise();
    }    

    save_data(  
        train_images_raw,
        train_labels_raw, 
        predict_images_raw,
        NUM_DATASET_ELEMENTS,
        NUM_PREDICT_ELEMENTS,
        IMAGE_SIZE,
        NUM_CLASSES
    ) 
    {        
        var dfd = jQuery.Deferred();
        var self=this;
        this.save_train_data(
            train_images_raw,
            train_labels_raw, 
            NUM_DATASET_ELEMENTS,
            IMAGE_SIZE,
            NUM_CLASSES
        ).then(function() {
            self.save_predict_data(
                predict_images_raw,
                NUM_PREDICT_ELEMENTS,
                IMAGE_SIZE                
            ).then(function() {
                dfd.resolve(  );
            });
        });
        return dfd.promise();
    }

    async get_prediction_data(
        NUM_PREDICT_ELEMENTS,
        IMAGE_SIZE
    ) {        
        var predict_images_raw   = new Float32Array(NUM_PREDICT_ELEMENTS * (IMAGE_SIZE));
        await this.db.predict_images.orderBy('id').each(function(img) {
            const image_offset=(img.id*IMAGE_SIZE);            
            for (var i=image_offset; i< image_offset+IMAGE_SIZE;i++ ){
                predict_images_raw[i] = img.predict_image[i-image_offset];
            }
        }).catch(function(error) {
            alert ("Ooops: " + error);
        });        
        const ret =
        {
            predict_images_raw: predict_images_raw
        };
        return ret;        
    };

    async get_training_data(
        NUM_DATASET_ELEMENTS,
        IMAGE_SIZE,
        NUM_CLASSES
    ) {        
        var train_images_raw   = new Float32Array(NUM_DATASET_ELEMENTS * (IMAGE_SIZE));
        var train_labels_raw   = new Uint8Array(NUM_DATASET_ELEMENTS * (NUM_CLASSES));        

        await this.db.images.orderBy('id').each(function(img) {
            const image_offset=(img.id*IMAGE_SIZE);
            const label_offset=(img.id*NUM_CLASSES);
            for (var i=image_offset; i< image_offset+IMAGE_SIZE;i++ ){
                train_images_raw[i] = img.train_image[i-image_offset];
            }
            for (var i=label_offset; i< label_offset+NUM_CLASSES;i++ ){
                train_labels_raw[i] = img.train_label[i-label_offset];
            }
        }).catch(function(error) {
            alert ("Ooops: " + error);
        });        
        const ret =
        {
            train_images_raw: train_images_raw, 
            train_labels_raw: train_labels_raw
        };
        return ret;        
    };

    get_images_count() {
        var dfd = jQuery.Deferred();
        this.db.images.count()         
        .then((val) => {
            dfd.resolve( val );
        });        
        return dfd.promise();
    };
}