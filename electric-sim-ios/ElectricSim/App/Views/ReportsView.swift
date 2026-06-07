//
//  ReportsView.swift
//  ElectricSim
//
//  დატვირთვის გრაფის სიმულაცია + ცალხაზოვანი ნახაზი (SLD) და ექსპორტი.
//  CSV — ყველასთვის; SLD PNG ექსპორტი — უფასოში 1, Pro-ში ულიმიტო.
//

import SwiftUI
import UIKit

struct ReportsView: View {
    @EnvironmentObject var store: StoreManager
    @EnvironmentObject var game: GameState
    let board: Board

    private let solver = CircuitSolver()
    @State private var tab = 0
    @State private var shareURL: URL?
    @State private var showShare = false
    @State private var showPaywall = false

    private var report: LoadReport { solver.loadReport(board) }
    private var sld: SingleLineDiagram { solver.singleLineDiagram(board) }

    var body: some View {
        VStack(spacing: 0) {
            Picker("", selection: $tab) {
                Text("გრაფი").tag(0)
                Text("ნახაზი").tag(1)
                Text("რეკომენდაცია").tag(2)
            }
            .pickerStyle(.segmented)
            .padding()

            switch tab {
            case 0: loadGraph
            case 1: sldTab
            default: recommendationsTab
            }
        }
        .navigationTitle("ანგარიში")
        .navigationBarTitleDisplayMode(.inline)
        .sheet(isPresented: $showShare) {
            if let url = shareURL { ActivityView(items: [url]) }
        }
        .sheet(isPresented: $showPaywall) { PaywallView().environmentObject(store) }
    }

    // MARK: - დატვირთვის გრაფი

    private var loadGraph: some View {
        let rep = report
        let maxA = max(rep.lines.map { $0.currentA }.max() ?? 1, 0.1)
        return ScrollView {
            VStack(alignment: .leading, spacing: 16) {
                summaryCard(rep)

                Text("დატვირთვები").font(.headline)
                ForEach(rep.lines) { line in
                    VStack(alignment: .leading, spacing: 3) {
                        HStack {
                            Circle().fill(line.phase.swiftUIColor).frame(width: 10, height: 10)
                            Text(line.name).font(.subheadline)
                            Spacer()
                            Text(String(format: "%.2f A", line.currentA))
                                .font(.caption.monospacedDigit())
                                .foregroundStyle(line.powered ? .primary : .secondary)
                        }
                        GeometryReader { geo in
                            ZStack(alignment: .leading) {
                                Capsule().fill(Color(.systemGray5)).frame(height: 10)
                                Capsule().fill(line.powered ? Color.yellow : Color(.systemGray3))
                                    .frame(width: geo.size.width * line.currentA / maxA, height: 10)
                            }
                        }
                        .frame(height: 10)
                    }
                }

                if rep.phase == .three { phaseBalanceCard(rep) }

                ShareLink(item: rep.csv()) {
                    Label("CSV ექსპორტი", systemImage: "square.and.arrow.up")
                        .frame(maxWidth: .infinity)
                }
                .buttonStyle(.bordered)
                .padding(.top, 4)
            }
            .padding()
        }
    }

    private func summaryCard(_ rep: LoadReport) -> some View {
        HStack {
            stat("სრული სიმძლავრე", String(format: "%.0f W", rep.totalPowerW))
            Divider()
            stat(rep.phase == .three ? "მაქს. ფაზის დენი" : "სრული დენი",
                 String(format: "%.1f A", rep.totalCurrentA))
        }
        .padding()
        .background(Color(.secondarySystemBackground), in: RoundedRectangle(cornerRadius: 12))
    }

    private func stat(_ title: String, _ value: String) -> some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(title).font(.caption2).foregroundStyle(.secondary)
            Text(value).font(.headline.monospacedDigit())
        }
        .frame(maxWidth: .infinity, alignment: .leading)
    }

    private func phaseBalanceCard(_ rep: LoadReport) -> some View {
        let phaseVals: [Double] = [rep.perPhase[.L1] ?? 0, rep.perPhase[.L2] ?? 0, rep.perPhase[.L3] ?? 0]
        let maxA = max(phaseVals.max() ?? 0.1, 0.1)
        return VStack(alignment: .leading, spacing: 8) {
            Text("ფაზების ბალანსი — დისბალანსი \(Int(rep.imbalance * 100))%")
                .font(.subheadline.bold())
                .foregroundStyle(rep.imbalance > 0.5 ? .orange : .primary)
            ForEach([Conductor.L1, .L2, .L3], id: \.self) { ph in
                HStack {
                    Text(ph.rawValue).font(.caption.bold()).frame(width: 28, alignment: .leading)
                        .foregroundStyle(ph.swiftUIColor)
                    GeometryReader { geo in
                        Capsule().fill(ph.swiftUIColor.opacity(0.7))
                            .frame(width: geo.size.width * (rep.perPhase[ph] ?? 0) / maxA, height: 12)
                    }.frame(height: 12)
                    Text(String(format: "%.1fA", rep.perPhase[ph] ?? 0))
                        .font(.caption2.monospacedDigit()).frame(width: 46, alignment: .trailing)
                }
            }
        }
        .padding()
        .background(Color(.secondarySystemBackground), in: RoundedRectangle(cornerRadius: 12))
    }

    // MARK: - რეკომენდაცია (მრჩეველი)

    private var recommendationsTab: some View {
        let recs = Recommender.boardAdvice(board)
        return ScrollView {
            VStack(alignment: .leading, spacing: 10) {
                Text("მრჩეველი გირჩევს სწორ ავტომატს, კაბელსა და დაცვას თითო დატვირთვისთვის.")
                    .font(.caption).foregroundStyle(.secondary)
                if recs.isEmpty {
                    Text("დაამატე კომპონენტები რეკომენდაციებისთვის.").foregroundStyle(.secondary)
                }
                ForEach(recs) { r in
                    HStack(alignment: .top, spacing: 10) {
                        Image(systemName: r.severity == .info ? "lightbulb.fill" : r.severity.icon)
                            .foregroundStyle(r.severity == .info ? .yellow : r.severity.color)
                        Text(r.message)
                            .font(.callout)
                            .fixedSize(horizontal: false, vertical: true)
                        Spacer(minLength: 0)
                    }
                    .padding(10)
                    .background(Color(.secondarySystemBackground), in: RoundedRectangle(cornerRadius: 10))
                }
            }
            .padding()
        }
    }

    // MARK: - ცალხაზოვანი ნახაზი

    private var sldTab: some View {
        VStack(spacing: 0) {
            ScrollView([.horizontal, .vertical]) {
                SLDDiagramView(sld: sld).padding(20)
            }
            Divider()
            exportBar
        }
    }

    private var exportBar: some View {
        let allowed = game.canExportSLD(isPro: store.isPro)
        return VStack(spacing: 4) {
            Button {
                exportSLD(allowed: allowed)
            } label: {
                Label(allowed ? "ნახაზის ექსპორტი (PNG)" : "ექსპორტი — Pro",
                      systemImage: allowed ? "square.and.arrow.up" : "lock.fill")
                    .frame(maxWidth: .infinity)
            }
            .buttonStyle(.borderedProminent)
            .tint(.yellow)
            if !store.isPro {
                Text("უფასო ვერსია: \(min(game.sldExportCount, GameState.freeSLDExports))/\(GameState.freeSLDExports) ექსპორტი გამოყენებულია")
                    .font(.caption2).foregroundStyle(.secondary)
            }
        }
        .padding()
    }

    @MainActor
    private func exportSLD(allowed: Bool) {
        guard allowed else { showPaywall = true; return }
        let renderer = ImageRenderer(content: SLDDiagramView(sld: sld).padding(20).background(Color.white))
        renderer.scale = 3
        guard let img = renderer.uiImage, let data = img.pngData() else { return }
        let url = FileManager.default.temporaryDirectory.appendingPathComponent("single-line-diagram.png")
        do {
            try data.write(to: url)
            game.recordSLDExport()
            shareURL = url
            showShare = true
        } catch { }
    }
}

// MARK: - SLD drawing

struct SLDDiagramView: View {
    let sld: SingleLineDiagram

    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            Text(sld.phase == .three ? "ცალხაზოვანი ნახაზი — 3 ფაზა" : "ცალხაზოვანი ნახაზი — 1 ფაზა")
                .font(.headline).padding(.bottom, 8)

            // შემომავალი ჯაჭვი (ვერტიკალურად)
            ForEach(Array(sld.incomer.enumerated()), id: \.element.id) { idx, node in
                nodeBox(node)
                if idx < sld.incomer.count - 1 || !sld.circuits.isEmpty {
                    connector()
                }
            }

            // ხაზები (ჰორიზონტალურად)
            if !sld.circuits.isEmpty {
                HStack(alignment: .top, spacing: 16) {
                    ForEach(sld.circuits) { circuit in
                        VStack(spacing: 0) {
                            if let brk = circuit.breaker {
                                nodeBox(brk); connector()
                            }
                            if let csa = circuit.csaMm2 {
                                Text(String(format: "%.1fmm² %@", csa, circuit.cableType.georgianName))
                                    .font(.system(size: 8)).foregroundStyle(.secondary)
                                connector()
                            }
                            nodeBox(circuit.load, highlight: true)
                        }
                    }
                }
                .padding(.top, 4)
            }
        }
    }

    private func nodeBox(_ node: SLDNode, highlight: Bool = false) -> some View {
        VStack(spacing: 1) {
            Image(systemName: node.kind.sfSymbol).font(.system(size: 16))
                .foregroundStyle(highlight ? .orange : .primary)
            Text(node.title).font(.system(size: 10, weight: .semibold))
            if !node.subtitle.isEmpty {
                Text(node.subtitle).font(.system(size: 8)).foregroundStyle(.secondary)
            }
        }
        .padding(8)
        .frame(minWidth: 88)
        .background(Color(.secondarySystemBackground), in: RoundedRectangle(cornerRadius: 8))
        .overlay(RoundedRectangle(cornerRadius: 8).stroke(Color.gray.opacity(0.4)))
    }

    private func connector() -> some View {
        Rectangle().fill(Color.primary.opacity(0.5)).frame(width: 2, height: 14)
    }
}

// MARK: - Share sheet

struct ActivityView: UIViewControllerRepresentable {
    let items: [Any]
    func makeUIViewController(context: Context) -> UIActivityViewController {
        UIActivityViewController(activityItems: items, applicationActivities: nil)
    }
    func updateUIViewController(_ vc: UIActivityViewController, context: Context) {}
}
