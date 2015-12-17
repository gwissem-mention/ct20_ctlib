<html>
    <head>
        <style type="text/css">
            body
            {
                padding:        4px;
                font-family:    helvetica,arial;
                color:          #232323;
            }

            p
            {
                padding:        8px;
                border-radius:  4px;
                font-size:      1.1em;
            }

            #notice
            {
                font-weight:    bold;
                font-size:      1.1em;                                    
                background:     #c1e8c5;
                border:         1px solid #6ea174;
                margin-bottom:  10px;
            }

            .prod #notice
            {
                background:     #e79a98;
                border-color:   #b0302d;
            }

            #notice span
            {
                display:        block;
                font-size:      .8em;
                padding-top:    3px;
            }

            #sleep
            {
                color:          #666666;
            }

            #sleep span
            {
                display:        block;
                font-weight:    bold;
                margin-top:     8px;
            }

            
        </style>
    </head>
    <body class="<?=$record['extra']['environment']?>">
        <p id="notice">
            <?php if ($record['extra']['environment'] == 'prod'): ?>
            This Occurred on Production!
            <?php else: ?>
            Relax. This Occurred on <?=ucfirst($record['extra']['environment'])?>.
            <?php endif; ?>
            <span>Host: <?=$record['__hostname__']?></span>
        </p>

        <p id="message">
            At least <?=$record['thresholdCount']?> errors have been logged on
            this site in the past <?=$record['thresholdMinutes']?> minutes.
        </p>

        <p id="sleep">
            You won't be emailed any more errors for the next
            <?=$record['sleepMinutes']?> minutes to avoid overwhelming your inbox.
            <span>NOTE: This doesn't mean errors are not being logged.</span>
        </p>
    </body>
</html>
