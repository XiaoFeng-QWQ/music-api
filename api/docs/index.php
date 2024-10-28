<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="shortcut icon" href="favicon.png">
    <title>Meting-API 参数说明</title>
    <style>
        /* 全局样式 */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            color: #333;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        h1 {
            text-align: center;
            color: #333;
            margin-top: 20px;
            font-size: 2em;
        }

        .container {
            box-sizing: border-box;
            margin: 0 auto;
            padding: 16px;
            width: 96%;
            max-width: 800px;
            flex: 1;
            /* 使容器内容占据可用空间 */
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            overflow: hidden;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
            transition: background-color 0.3s ease;
        }

        th {
            background-color: #f2f2f2;
            color: #333;
        }

        ul {
            margin: 8px 0;
            padding-left: 20px;
            list-style: disc;
            color: #555;
        }

        a {
            color: #1a73e8;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        footer {
            text-align: center;
            padding: 20px;
            font-size: 0.9em;
            color: #666;
            background-color: #f1f1f1;
            border-top: 1px solid #ddd;
            position: relative;
            bottom: 0;
        }
    </style>
</head>

<body>

    <h1>音乐API（仅支持网易云）</h1>

    <div class="container">
        <table>
            <tr>
                <th>参数</th>
                <th>说明</th>
            </tr>
            <tr>
                <td>type</td>
                <td>
                    返回类型
                    <ul>
                        <li>name - 歌曲名</li>
                        <li>artist - 歌手</li>
                        <li>url - 链接</li>
                        <li>pic - 封面</li>
                        <li>lrc - 歌词</li>
                        <li>song - 单曲</li>
                        <li>playlist - 歌单</li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td>id</td>
                <td>类型ID（封面ID/单曲ID/歌单ID）</td>
            </tr>
        </table>

        <table>
            <tr>
                <th>示例链接</th>
            </tr>
            <tr>
                <td>
                    <a href="<?php echo API_URI ?>?type=url&id=1386259535" target="_blank" rel="noopener noreferrer">
                        <?php echo API_URI ?>?type=url&id=1386259535
                    </a>
                </td>
            </tr>
            <tr>
                <td>
                    <a href="<?php echo API_URI ?>?type=song&id=1988233212" target="_blank" rel="noopener noreferrer">
                        <?php echo API_URI ?>?type=song&id=1988233212
                    </a>
                </td>
            </tr>
            <tr>
                <td>
                    <a href="<?php echo API_URI ?>?type=playlist&id=7783760543" target="_blank" rel="noopener noreferrer">
                        <?php echo API_URI ?>?type=playlist&id=7783760543
                    </a>
                </td>
            </tr>
        </table>
    </div>

    <footer>
        此 API 基于 <a href="https://github.com/metowolf/Meting" target="_blank" rel="noopener noreferrer">Meting</a> 构建。
    </footer>

</body>

</html>
