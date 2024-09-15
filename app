 :root {
        --primary: #6366f1;
        --secondary: #ec4899;
        --background: #f0f4ff;
        --text: #1e1e2f;
        --input-border: #d1d5db;
        --error-color: #e02424;
        --card-bg: #ffffff;
        --shadow-light: rgba(99, 102, 241, 0.15);
    }

    body {
        font-family: 'Poppins', sans-serif;
        background: var(--background);
        color: var(--text);
        margin: 0;
    }
.admin-panel {
    margin: 100px 0 20px 0;
    padding-left: 240px;
    padding-right: 20px;
    padding-top: 25px;
    padding-bottom: 25px;
    background: none; /* or var(--background) */
    box-shadow: none;
    border-radius: 0;
    width: 100%;
    box-sizing: border-box;
}

    h2 {
        margin-bottom: 28px;
        text-align: center;
        color: var(--primary);
        font-weight: 700;
        font-size: 28px;
    }

    .message {
        margin-bottom: 20px;
        padding: 12px;
        border-radius: 8px;
        text-align: center;
        font-weight: 600;
        font-size: 16px;
        color: green;
        background-color: #e6ffe6;
        border: 1px solid #c6ffc6;
    }

    table {
        border-collapse: collapse;
        width: 100%;
        margin-bottom: 25px;
        background: var(--card-bg);
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    th, td {
        border: 1px solid var(--input-border);
        padding: 12px 8px;
        text-align: left;
    }

    th {
        background-color: var(--primary);
        color: white;
        font-weight: 600;
    }

    td {
        background-color: var(--card-bg);
    }

    tr:nth-child(even) td {
        background-color: #f9f9f9;
    }

    button {
        padding: 8px 15px;
        background-color: var(--primary);
        color: white;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
    }

    button:hover {
        background-color: #4b47c9;
    }

    .tabs {
        margin-bottom: 20px;
    }

    .tabs a {
        padding: 10px 20px;
        background-color: #e0e0e0;
        color: #333;
        text-decoration: none;
        border-radius: 5px;
        margin-right: 10px;
        font-weight: 600;
    }

    .tabs a.active {
        background-color: var(--primary);
        color: white;
    }

    .approved {
        background-color: #e6ffe6;
    }

    .blocked {
        background-color: #ffe6e6;
    }

    .dataTables_filter {
        float: right;
        margin-bottom: 10px;
    }