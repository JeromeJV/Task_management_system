let supervisorChart = null;

let statusChartData = {
    completed: [],
    pending: [],
    overdue: []
};

const monthLabels = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December'
];


// =====================================================
// PAGE SWITCHING
// =====================================================

function showPage(pageName) {

    const pages = document.querySelectorAll('.page');

    pages.forEach(function (page) {
        page.style.display = 'none';
    });

    const selectedPage = document.getElementById(
        'page-' + pageName
    );

    if (selectedPage) {
        selectedPage.style.display = 'block';
    }

    // Remove active
    document
        .querySelectorAll('.side-btn')
        .forEach(function (button) {
            button.classList.remove('active');
        });


    // Dashboard
    if (pageName === 'dashboard') {

        const dashboard =
            document.getElementById('navDashboard');

        if (dashboard) {
            dashboard.classList.add('active');
        }
    }


    // Task
    if (pageName === 'task') {

        const task =
            document.getElementById('navTask');

        if (task) {
            task.classList.add('active');
        }
    }
}


// =====================================================
// LOAD MAIN GRAPH
// =====================================================

async function loadChart() {

    try {

        const response = await fetch(
            'config/chart_data.php',
            {
                cache: 'no-store'
            }
        );

        if (!response.ok) {

            throw new Error(
                'Chart request failed: ' +
                response.status
            );
        }

        const rawText = await response.text();

        console.log(
            'Chart response:',
            rawText
        );


        // Check kung PHP error ang bumalik
        if (
            rawText.trim().startsWith('<')
        ) {

            console.error(
                'PHP ERROR SA chart_data.php:',
                rawText
            );

            return;
        }


        const chartData = JSON.parse(rawText);

        console.log(
            'Chart data:',
            chartData
        );


        const canvas =
            document.getElementById('myChart');


        if (!canvas) {

            console.error(
                'myChart wala sa page.'
            );

            return;
        }


        const ctx =
            canvas.getContext('2d');


        // Destroy old chart
        if (supervisorChart) {
            supervisorChart.destroy();
        }


        // =================================================
        // CREATE GRAPH
        // =================================================

        supervisorChart = new Chart(
            ctx,
            {
                type: 'line',

                data: {

                    labels: monthLabels,

                    datasets: [
                        {

                            label:
                                'Product Done Quantity',

                            data:
                                chartData,

                            borderColor:
                                '#2f7d55',

                            backgroundColor:
                                'rgba(47, 125, 85, 0.18)',

                            borderWidth:
                                3,

                            pointRadius:
                                5,

                            pointHoverRadius:
                                8,

                            pointBackgroundColor:
                                '#2f7d55',

                            pointBorderColor:
                                '#2f7d55',

                            fill:
                                true,

                            tension:
                                0.4
                        }
                    ]
                },


                options: {

                    responsive:
                        true,

                    maintainAspectRatio:
                        false,


                    animations: {

                        tension: {

                            duration:
                                1000,

                            easing:
                                'linear',

                            from:
                                0.8,

                            to:
                                0.4,

                            loop:
                                true
                        }
                    },


                    interaction: {

                        mode: 'index',

                        intersect: false
                    },


                    plugins: {

                        legend: {
                            display: true
                        },

                        tooltip: {
                            enabled: true
                        }
                    },


                    scales: {

                        x: {

                            title: {

                                display:
                                    true,

                                text:
                                    'Months (January - December)'
                            }
                        },


                        y: {

                            beginAtZero:
                                true,

                            min:
                                0,

                            max:
                                100000,

                            ticks: {

                                stepSize:
                                    20000
                            },


                            title: {

                                display:
                                    true,

                                text:
                                    'Total Completed Quantity'
                            }
                        }
                    }
                }
            }
        );


        // Load status data
        await loadStatusData();

    }

    catch (error) {

        console.error(
            'ERROR SA GRAPH:',
            error
        );
    }
}


// =====================================================
// LOAD STATUS GRAPH DATA
// =====================================================

async function loadStatusData() {

    try {

        const response =
            await fetch(
                'config/status_chart_data.php',
                {
                    cache: 'no-store'
                }
            );


        if (!response.ok) {

            throw new Error(
                'Status request failed: ' +
                response.status
            );
        }


        const rawText =
            await response.text();


        console.log(
            'Status response:',
            rawText
        );


        // Check PHP error
        if (
            rawText.trim().startsWith('<')
        ) {

            console.error(
                'PHP ERROR SA status_chart_data.php:',
                rawText
            );

            return;
        }


        const data =
            JSON.parse(rawText);


        if (data.error) {

            console.error(
                'Status PHP error:',
                data.error
            );

            return;
        }


        statusChartData.completed =
            Array.isArray(data.completed)
                ? data.completed
                : [];


        statusChartData.pending =
            Array.isArray(data.pending)
                ? data.pending
                : [];


        statusChartData.overdue =
            Array.isArray(data.overdue)
                ? data.overdue
                : [];


        console.log(
            'STATUS DATA:',
            statusChartData
        );

    }

    catch (error) {

        console.error(
            'ERROR LOADING STATUS DATA:',
            error
        );
    }
}


// =====================================================
// FIND LAST MONTH WITH DATA
// =====================================================

function findLatestMonth(data) {

    if (!Array.isArray(data)) {
        return -1;
    }


    for (
        let i = data.length - 1;
        i >= 0;
        i--
    ) {

        if (
            Number(data[i]) > 0
        ) {

            return i;
        }
    }


    return -1;
}


// =====================================================
// CLICK COMPLETED / PENDING / OVERDUE
// =====================================================

function focusGraphStatus(status) {

    if (!supervisorChart) {

        console.error(
            'Chart hindi pa ready.'
        );

        return;
    }


    let data = [];

    let title = '';

    let color = '';

    let backgroundColor = '';


    // =================================================
    // COMPLETED
    // =================================================

    if (
        status === 'completed'
    ) {

        data =
            statusChartData.completed;

        title =
            'Completed Quantity';

        color =
            '#2f7d55';

        backgroundColor =
            'rgba(47, 125, 85, 0.18)';
    }


    // =================================================
    // PENDING
    // =================================================

    else if (
        status === 'pending'
    ) {

        data =
            statusChartData.pending;

        title =
            'Pending Quantity';

        color =
            '#e88a00';

        backgroundColor =
            'rgba(232, 138, 0, 0.18)';
    }


    // =================================================
    // OVERDUE
    // =================================================

    else if (
        status === 'overdue'
    ) {

        data =
            statusChartData.overdue;

        title =
            'Overdue Quantity';

        color =
            '#e8483a';

        backgroundColor =
            'rgba(232, 72, 58, 0.18)';
    }


    else {
        return;
    }


    // =================================================
    // MAKE SURE DATA HAS 12 MONTHS
    // =================================================

    if (!Array.isArray(data)) {
        data = [];
    }


    while (data.length < 12) {
        data.push(0);
    }


    if (data.length > 12) {
        data = data.slice(0, 12);
    }


    // =================================================
    // FIND MONTH
    // =================================================

    const monthIndex =
        findLatestMonth(data);


    console.log(
        'STATUS:',
        status
    );


    console.log(
        'MONTH INDEX:',
        monthIndex
    );


    // =================================================
    // CHANGE GRAPH DATA
    // =================================================

    const dataset =
        supervisorChart.data.datasets[0];


    dataset.data =
        data;


    dataset.label =
        title;


    dataset.borderColor =
        color;


    dataset.backgroundColor =
        backgroundColor;


    dataset.pointBackgroundColor =
        color;


    dataset.pointBorderColor =
        color;


    // Change Y-axis title
    if (
        supervisorChart.options.scales &&
        supervisorChart.options.scales.y &&
        supervisorChart.options.scales.y.title
    ) {

        supervisorChart
            .options
            .scales
            .y
            .title
            .text = title;
    }


    // Update graph
    supervisorChart.update();


    // =================================================
    // SHOW STATUS MESSAGE
    // =================================================

    const statusLabel =
        document.getElementById(
            'graphStatusLabel'
        );


    if (statusLabel) {

        statusLabel.textContent =
            title;


        statusLabel.style.display =
            'block';


        statusLabel.style.borderLeft =
            '5px solid ' + color;


        statusLabel.style.color =
            color;
    }


    // =================================================
    // SCROLL TO GRAPH
    // =================================================

    const graph =
        document.getElementById(
            'productProgressPanel'
        );


    if (graph) {

        graph.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    }


    // =================================================
    // HIGHLIGHT MONTH
    // =================================================

    if (monthIndex >= 0) {

        setTimeout(
            function () {

                try {

                    const meta =
                        supervisorChart
                            .getDatasetMeta(0);


                    if (
                        meta &&
                        meta.data &&
                        meta.data[monthIndex]
                    ) {

                        const point =
                            meta.data[
                                monthIndex
                            ];


                        supervisorChart
                            .setActiveElements([
                                {
                                    datasetIndex:
                                        0,

                                    index:
                                        monthIndex
                                }
                            ]);


                        // Show tooltip
                        if (
                            supervisorChart.tooltip &&
                            typeof supervisorChart.tooltip.setActiveElements ===
                                'function'
                        ) {

                            supervisorChart
                                .tooltip
                                .setActiveElements(
                                    [
                                        {
                                            datasetIndex:
                                                0,

                                            index:
                                                monthIndex
                                        }
                                    ],
                                    {
                                        x:
                                            point.x,

                                        y:
                                            point.y
                                    }
                                );
                        }


                        supervisorChart.update();


                        console.log(
                            'Focused:',
                            monthLabels[
                                monthIndex
                            ]
                        );
                    }

                }

                catch (error) {

                    console.error(
                        'Error highlighting graph point:',
                        error
                    );
                }

            },
            600
        );
    }
}


// =====================================================
// PROGRESS REPORTS
// PRODUCTION ONLY
// =====================================================
//
// IMPORTANT:
// LOGISTICS / DELIVERY REPORTS ARE NOT DISPLAYED.
//
// Kahit may old Logistics data sa JavaScript/PHP,
// Production records lang ang papayagan dito.
//
// =====================================================

function loadProgressReports() {

    const reports =
        Array.isArray(
            window.supervisorProgressReports
        )
            ? window.supervisorProgressReports
            : [];


    // =================================================
    // PRODUCTION ONLY
    // =================================================

    const productionReports =
        reports.filter(
            function (report) {

                return String(
                    report.source || ''
                ).toLowerCase() === 'production';

            }
        );


    const panel =
        document.querySelector(
            '.progress-report-panel'
        );


    if (!panel) {

        console.warn(
            'progress-report-panel wala.'
        );

        return;
    }


    // =================================================
    // FIND / CREATE REPORT LIST
    // =================================================

    let reportList =
        panel.querySelector(
            '.progress-report-list'
        );


    if (!reportList) {

        reportList =
            document.createElement('div');

        reportList.className =
            'progress-report-list';


        panel.appendChild(
            reportList
        );
    }


    reportList.innerHTML = '';


    // =================================================
    // EMPTY
    // =================================================

    if (productionReports.length === 0) {

        const empty =
            document.createElement('div');


        empty.className =
            'progress-report-empty';


        empty.textContent =
            'No Production progress reports available yet.';


        reportList.appendChild(
            empty
        );


        return;
    }


    // =================================================
    // CREATE PRODUCTION REPORT ITEMS ONLY
    // =================================================

    productionReports.forEach(
        function (report) {

            const item =
                document.createElement('div');


            item.className =
                'progress-report-item';


            // =================================================
            // SOURCE
            // =================================================

            const source =
                document.createElement('div');


            source.className =
                'progress-report-source';


            const icon =
                document.createElement('span');


            icon.className =
                'progress-report-icon';


            // =================================================
            // PRODUCTION ONLY
            // =================================================

            icon.textContent =
                '🏭';


            source.textContent =
                'PRODUCTION';


            source.prepend(
                icon
            );


            // =================================================
            // TITLE
            // =================================================

            const title =
                document.createElement('div');


            title.className =
                'progress-report-title';


            title.textContent =
                report.title ||
                'Unnamed Task';


            // =================================================
            // META
            // =================================================

            const meta =
                document.createElement('div');


            meta.className =
                'progress-report-meta';


            const status =
                String(
                    report.status || 'Pending'
                );


            let statusText =
                'In Progress';


            let statusClass =
                'in-progress';


            if (
                status.toLowerCase() ===
                'completed'
            ) {

                statusText =
                    'Completed';

                statusClass =
                    'completed';
            }


            else if (
                status.toLowerCase() ===
                'pending'
            ) {

                statusText =
                    'In Progress';

                statusClass =
                    'in-progress';
            }


            meta.innerHTML =
                '<span class="progress-report-status ' +
                statusClass +
                '">' +
                statusText +
                '</span>';


            // =================================================
            // PROGRESS LINE
            // =================================================

            const progress =
                document.createElement('div');


            progress.className =
                'progress-report-track';


            const progressBar =
                document.createElement('div');


            progressBar.className =
                'progress-report-fill ' +
                statusClass;


            let progressValue =
                45;


            if (
                statusClass === 'completed'
            ) {

                progressValue =
                    100;
            }


            progressBar.style.width =
                progressValue + '%';


            progress.appendChild(
                progressBar
            );


            // =================================================
            // CONTENT
            // =================================================

            const content =
                document.createElement('div');


            content.className =
                'progress-report-content';


            content.appendChild(
                source
            );


            content.appendChild(
                title
            );


            content.appendChild(
                meta
            );


            content.appendChild(
                progress
            );


            item.appendChild(
                content
            );


            reportList.appendChild(
                item
            );
        }
    );
}


// =====================================================
// OPEN PROGRESS EDIT MODAL
// COMPATIBLE SA PHP BUTTON MO
// =====================================================

function openProgressEdit(
    id,
    product,
    target,
    due,
    quantity
) {

    const modal =
        document.getElementById(
            'progressEditModal'
        );


    if (!modal) {

        console.warn(
            'progressEditModal wala sa supervisor.php'
        );

        return;
    }


    const idInput =
        document.getElementById(
            'editProgressId'
        );


    const productInput =
        document.getElementById(
            'editProductName'
        );


    const targetInput =
        document.getElementById(
            'editTarget'
        );


    const quantityInput =
        document.getElementById(
            'editQuantity'
        );


    const dueInput =
        document.getElementById(
            'editDueDate'
        );


    // =================================================
    // SET VALUES
    // =================================================

    if (idInput) {
        idInput.value =
            id || '';
    }


    if (productInput) {
        productInput.value =
            product || '';
    }


    if (targetInput) {
        targetInput.value =
            target || 0;
    }


    if (quantityInput) {
        quantityInput.value =
            quantity || 0;
    }


    if (dueInput) {
        dueInput.value =
            due || '';
    }


    // Update preview
    updateEditProgressPreview();


    // Show modal
    modal.classList.add(
        'show'
    );


    modal.setAttribute(
        'aria-hidden',
        'false'
    );


    document.body.classList.add(
        'modal-open'
    );


    // Focus product field
    setTimeout(
        function () {

            if (productInput) {
                productInput.focus();
            }

        },
        100
    );
}


// =====================================================
// CLOSE PROGRESS EDIT MODAL
// =====================================================

function closeProgressEdit() {

    const modal =
        document.getElementById(
            'progressEditModal'
        );


    if (!modal) {
        return;
    }


    modal.classList.remove(
        'show'
    );


    modal.setAttribute(
        'aria-hidden',
        'true'
    );


    document.body.classList.remove(
        'modal-open'
    );
}


// =====================================================
// UPDATE EDIT PROGRESS PREVIEW
// =====================================================

function updateEditProgressPreview() {

    const targetInput =
        document.getElementById(
            'editTarget'
        );


    const quantityInput =
        document.getElementById(
            'editQuantity'
        );


    const percentage =
        document.getElementById(
            'editProgressPreview'
        );


    const bar =
        document.getElementById(
            'editProgressPreviewBar'
        );


    if (
        !targetInput ||
        !quantityInput ||
        !percentage ||
        !bar
    ) {

        return;
    }


    const target =
        Number(
            targetInput.value
        );


    const quantity =
        Number(
            quantityInput.value
        );


    let progress = 0;


    if (
        Number.isFinite(target) &&
        target > 0
    ) {

        progress =
            Math.round(
                (
                    quantity /
                    target
                ) * 100
            );
    }


    progress =
        Math.max(
            0,
            Math.min(
                100,
                progress
            )
        );


    percentage.textContent =
        progress + '%';


    bar.style.width =
        progress + '%';


    bar.classList.remove(
        'completed',
        'in-progress',
        'overdue',
        'pending'
    );


    if (
        progress >= 100
    ) {

        bar.classList.add(
            'completed'
        );

    }

    else if (
        progress > 0
    ) {

        bar.classList.add(
            'in-progress'
        );

    }

    else {

        bar.classList.add(
            'pending'
        );
    }
}


// =====================================================
// CLOSE MODAL WHEN CLICKING OUTSIDE
// =====================================================

document.addEventListener(
    'click',
    function (event) {

        const modal =
            document.getElementById(
                'progressEditModal'
            );


        if (!modal) {
            return;
        }


        if (
            event.target === modal
        ) {

            closeProgressEdit();
        }
    }
);


// =====================================================
// KEYBOARD SUPPORT
// =====================================================

document.addEventListener(
    'keydown',
    function (event) {

        if (
            event.key === 'Escape'
        ) {

            closeProgressEdit();
        }
    }
);


// =====================================================
// START
// =====================================================

document.addEventListener(
    'DOMContentLoaded',
    function () {

        console.log(
            'Supervisor JS loaded.'
        );


        // Load chart
        loadChart();


        // =================================================
        // LOAD PRODUCTION REPORTS ONLY
        // =================================================

        loadProgressReports();


        // =================================================
        // PROGRESS PREVIEW
        // =================================================

        const targetInput =
            document.getElementById(
                'editTarget'
            );


        const quantityInput =
            document.getElementById(
                'editQuantity'
            );


        if (targetInput) {

            targetInput.addEventListener(
                'input',
                updateEditProgressPreview
            );
        }


        if (quantityInput) {

            quantityInput.addEventListener(
                'input',
                updateEditProgressPreview
            );
        }


        // =================================================
        // DEFAULT DASHBOARD
        // =================================================

        const dashboard =
            document.getElementById(
                'page-dashboard'
            );


        if (dashboard) {
            dashboard.style.display =
                'block';
        }
    }
);