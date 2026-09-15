    <section id="listView">
      <div id="filters" class="filters"<?= $pageTab !== 'queue' ? ' hidden' : '' ?>><input id="searchInput" type="search" placeholder="Search name, matric number or reference"><select id="reasonFilter"><option value="">All reasons</option><option value="loststolen">Lost / Stolen</option><option value="damaged">Damaged</option></select></div>
      <section id="applicationList" class="application-list"></section>
    </section>
    <section id="detailView" hidden><button id="backButton" class="back-button" type="button">← Back to list</button><article id="detailContent" class="detail-card"></article></section>
