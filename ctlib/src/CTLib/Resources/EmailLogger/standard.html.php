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

            #message
            {
                background:     #f0f0f0;
                border:         1px solid #bbbbbb;
            }

            #message span
            {
                display:        block;
                font-size:      .9em;
                font-family:    courier;
                color:          #666666;
                padding-top:    8px;
                margin-top:     8px;
                border-top:     1px solid #ffffff;
            }

            ul
            {
                list-style:     none;
                padding:        0;
            }

            li
            {
                padding:        2px 0;
                border-bottom:  1px solid #dadada;
                margin:         2px 0;
            }

            li span
            {
                float:          left;
                font-weight:    bold;
                width:          120px;
            }

            li.spacer
            {
                height:         10px;
                border-bottom:  none;
            }

        </style>
    </head>
    <body class="<?=$record['extra']['environment']?>">
        <p id="notice">
            <?php if ($record['extra']['environment'] == 'prod'): ?>
            This Error Occurred on Production!
            <?php else: ?>
            Relax. This Error Occurred on <?=ucfirst($record['extra']['environment'])?>.
            <?php endif; ?>
        </p>

        <p id="message">
            <?=str_replace("\n", "<br>", $record['message'])?>
            <span>@ <?=$record['extra']['file']?> #<?=$record['extra']['line']?></span>
        </p>

        <ul>
            <li><span>Site Name</span><?=$record['site']?></li>
            <li><span>Site ID</span><?=(isset($record['extra']['site_id']) ? $record['extra']['site_id'] : 'N/A')?></li>
            <li><span>App Version</span><?=(isset($record['extra']['app_version']) ? $record['extra']['app_version'] : 'N/A')?></li>
            <li class="spacer"></li>
            <li><span>Time</span><?=$record['datetime']->format('Y-m-d H:i')?></li>
            <li><span>Channel</span><?=$record['channel']?></li>
            <li><span>Thread</span><?=(isset($record['context']['_thread']) ? $record['context']['_thread'] : 'N/A')?></li>
        </ul>
    </body>
</html>
