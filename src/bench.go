package main

import (
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
)

const TOTAL_COMMITS = 10

func main() {
	flag.Parse()

	result := runBench()
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

func runBench() string {
	var results [TOTAL_COMMITS][2]interface{}

	for i := TOTAL_COMMITS - 1; i >= 0; i-- {
		exec.Command("git", "checkout", "master").Output()
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

		results[i] = [2]interface{}{
			// commit[0],
			commit[1],
			// b.N,
			// b.T.Seconds(),
			averagePerExecution(b.T.Nanoseconds(), int64(b.N)),
		}
		exec.Command("git", "checkout", "master").Output()
		exec.Command("git", "branch", "-D", "performance").Output()
	}

	b, err := json.MarshalIndent(results, "", "\t")
	checkerr(err)

	return string(b)
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
