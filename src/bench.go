package main

import (
	"database/sql"
	"encoding/json"
	"flag"
	"fmt"
	"log"
	"net/http"
	"os/exec"
	"runtime"
	"strconv"
	"strings"
	"testing"
	"time"

	_ "github.com/mattn/go-sqlite3"
)

const TOTAL_COMMITS = 10
const benchmarkResultsFileName = "bench_results"
const benchmarkResultsDriver = "db"

type dbResults struct {
}

func main() {
	flag.Parse()
	d := &dbResults{}
	result := runBench(d)
	http.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		fmt.Fprintf(
			w,
			TEMPLATE,
			string(result),
		)
	})
	fmt.Println("Listening")
	if "darwin" == runtime.GOOS {
		fmt.Println("opening browser")
		go func() {
			time.Sleep(3 * time.Second)
			exec.Command("/usr/bin/open", "http://localhost:8080/").Output()
		}()
	}
	http.ListenAndServe(":8080", nil)
}

func runBench(d *dbResults) string {
	var results [TOTAL_COMMITS][3]interface{}

	for i := TOTAL_COMMITS - 1; i >= 0; i-- {
		exec.Command("git", "checkout", "benchmark-db-results").Output()
		exec.Command("git", "branch", "-D", "performance").Output()
		exec.Command("git", "checkout", "-b", "performance").Output()
		exec.Command("git", "reset", "--hard", "HEAD~"+strconv.Itoa(i)).Output()
		out, err := exec.Command("git", "log", "--pretty=oneline", "-n1").Output()
		if err != nil {
			log.Fatal(err)
		}

		fmt.Print(string(out))

		commit := strings.Split(
			strings.Replace(
				strings.TrimSpace(string(out)), " ", ";", 1,
			),
			";",
		)

		b := testing.Benchmark(func(b *testing.B) {
			for n := 0; n < b.N; n++ {
				exec.Command("php", "test.php").Output()
			}
		})

		results[i] = [3]interface{}{
			commit[0],
			commit[1],
			// b.N,
			// b.T.Seconds(),
			averagePerExecution(b.T.Nanoseconds(), int64(b.N)),
		}
		exec.Command("git", "checkout", "master").Output()
		exec.Command("git", "branch", "-D", "performance").Output()
	}

	err := d.saveBenchResults(results)
	checkerr(err)

	b, err := toJson(results)
	checkerr(err)

	return string(b)
}

func toJson(results [TOTAL_COMMITS][3]interface{}) ([]byte, error) {
	resultsToJson := [TOTAL_COMMITS][2]interface{}{}
	for i, result := range results {
		resultToJson := [2]interface{}{
			result[1],
			result[2],
		}
		resultsToJson[i] = resultToJson
	}

	return json.MarshalIndent(resultsToJson, "", "\t")
}

func (d *dbResults) createTableIfNotExists(db *sql.DB) error {
	resultsQuery := `
		CREATE TABLE IF NOT EXISTS results (
			"id" integer NOT NULL PRIMARY KEY,
			"commitHash" varchar(128) NOT NULL, 
			"commitDesc" varchar(128) NOT NULL, 
			"average" REAL NOT NULL
		);
	`
	_, err := db.Exec(resultsQuery)
	if err != nil {
		return err
	}

	return nil
}
func (d *dbResults) saveBenchResults(results [TOTAL_COMMITS][3]interface{}) error {
	db, err := sql.Open("sqlite3", fmt.Sprintf("%s.db", benchmarkResultsFileName))
	if err != nil {
		return err
	}
	defer db.Close()

	err = d.createTableIfNotExists(db)
	if nil != err {
		return err
	}
	for _, result := range results {
		tx, err := db.Begin()
		if err != nil {
			return err
		}
		stmtstr := "insert into results(`commitHash`,`commitDesc`,average) values(?, ?, ?)"
		stmt, err := tx.Prepare(stmtstr)
		if err != nil {
			return err
		}
		_, err = stmt.Exec(result[0], result[1], result[2])
		if nil != err {
			return err
		}
		tx.Commit()
	}
	return nil
}

func averagePerExecution(t, n int64) float64 {
	return float64(t/n) / float64(1e9)
}

const TEMPLATE = `
  <html>
  <head>
    <script type="text/javascript"
          src="https://www.google.com/jsapi?autoload={
            'modules':[{
              'name':'visualization',
              'version':'1',
              'packages':['corechart']
            }]
          }"></script>

    <script type="text/javascript">
      google.setOnLoadCallback(drawChart);

      function drawChart() {
        var data = new google.visualization.DataTable();

	data.addColumn('string',"hash");
	// data.addColumn('string',"description");
	// data.addColumn('number',"iterations");
	// data.addColumn('number',"duration");
	data.addColumn('number',"average");
	data.addRows(%s)

        var options = {
          title: 'Benchmark',
          curveType: 'function',
          legend: { position: 'bottom' }
        };

        var chart = new google.visualization.LineChart(document.getElementById('curve_chart'));

        chart.draw(data, options);
      }
    </script>
  </head>
  <body>
    <div id="curve_chart" style="width: 900px; height: 500px"></div>
  </body>
</html>
`

func checkerr(err error) {
	if err != nil {
		panic(err)
	}
}
