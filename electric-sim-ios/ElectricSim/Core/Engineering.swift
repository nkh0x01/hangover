//
//  Engineering.swift
//  ElectricSim — Core
//
//  საინჟინრო გათვლები: ძაბვის ვარდნა (ΔU%), სელექტიურობა, მასალების ნუსხა (BOM).
//  წმინდა ლოგიკა (Foundation), გამოსაცდელი `swift test`-ით.
//

import Foundation

// MARK: - Voltage drop (ძაბვის ვარდნა)

public enum VoltageDrop {
    /// ხვედრითი წინაღობა (Ω·mm²/m) 20°C-ზე.
    public static func resistivity(_ cable: CableType) -> Double {
        cable == .copper ? 0.0175 : 0.0282
    }

    /// ΔU% — ძაბვის ვარდნა პროცენტებში.
    public static func percent(currentA: Double, lengthM: Double, csaMm2: Double,
                               cable: CableType, threePhase: Bool) -> Double {
        guard csaMm2 > 0, lengthM > 0, currentA > 0 else { return 0 }
        let u = threePhase ? Electrical.phaseToPhase : Electrical.phaseToNeutral
        let factor = threePhase ? Double(3).squareRoot() : 2.0   // 2L ერთფაზაზე, √3 სამფაზაზე
        let dropV = factor * resistivity(cable) * lengthM * currentA / csaMm2
        return u > 0 ? dropV / u * 100 : 0
    }

    /// დასაშვები ზღვარი (%) — განათება 3%, დანარჩენი 5% (IEC პრაქტიკა).
    public static func limitPct(for kind: ComponentKind) -> Double {
        (kind == .lamp || kind == .dimmer) ? 3 : 5
    }
}

// MARK: - Selectivity (სელექტიურობა / დისკრიმინაცია)

public enum Selectivity {
    /// კოეფიციენტი — ზედა ავტომატი დაბლა მდებარის მინიმუმ 1.6×-ჯერ უნდა აღემატებოდეს.
    public static let ratio: Double = 1.6

    /// არის თუ არა სელექტიური წყვილი (upstream ზემოთ, downstream დაბლა).
    public static func isSelective(upstream: Double, downstream: Double) -> Bool {
        guard downstream > 0 else { return true }
        return upstream >= downstream * ratio - 0.001
    }
}

// MARK: - Bill of materials (მასალების ნუსხა)

public struct BOMItem: Identifiable, Sendable {
    public let id: String          // templateid ან kind
    public let name: String
    public let quantity: Int
    public let unitPriceGEL: Double
    public var totalGEL: Double { Double(quantity) * unitPriceGEL }
}

public struct BillOfMaterials: Sendable {
    public let items: [BOMItem]
    public let cableTotalM: Double
    public let cablePriceGEL: Double
    public var componentsGEL: Double { items.reduce(0) { $0 + $1.totalGEL } }
    public var totalGEL: Double { componentsGEL + cablePriceGEL }
}

// MARK: - Protective chain + selectivity analysis

extension CircuitSolver {

    /// დატვირთვის დამცავი ავტომატების ჯაჭვი (ახლოდან შორს: branch → main).
    public func protectiveChain(_ board: Board, loadID: String) -> [Component] {
        guard let load = board.components.first(where: { $0.id == loadID }) else { return [] }
        let uf = UnionFind()
        for c in board.components { for p in c.ports { uf.makeSet(p.id) } }
        for w in board.wires { uf.union(w.fromPortID, w.toPortID) }
        for c in board.components where c.kind.isConnector {
            if let f = c.ports.first { for p in c.ports.dropFirst() { uf.union(f.id, p.id) } }
        }
        func net(_ id: String) -> String { uf.find(id) }

        struct Edge { let comp: Component; let inNet: String; let outNet: String }
        var edges: [Edge] = []
        for comp in board.components where comp.kind.isSeriesDevice {
            for c in Set(comp.ports.map { $0.conductor }) where c.isHot {
                guard let i = comp.port(side: .input, conductor: c),
                      let o = comp.port(side: .output, conductor: c) else { continue }
                edges.append(Edge(comp: comp, inNet: net(i.id), outNet: net(o.id)))
            }
        }
        var supplyNets = Set<String>()
        if let s = board.supply {
            for p in s.ports where p.side == .output && p.conductor.isHot { supplyNets.insert(net(p.id)) }
        }
        var parentDevice: [String: Component] = [:]
        var parentNet: [String: String] = [:]
        var visited = supplyNets
        var queue = Array(supplyNets)
        while !queue.isEmpty {
            let cur = queue.removeFirst()
            for e in edges {
                let nb: String? = e.inNet == cur ? e.outNet : (e.outNet == cur ? e.inNet : nil)
                if let nb, !visited.contains(nb) {
                    visited.insert(nb); parentDevice[nb] = e.comp; parentNet[nb] = cur; queue.append(nb)
                }
            }
        }
        let linePort = load.port(conductor: load.kind.isThreePhaseLoad ? .L1 : .L)
        guard var cur = linePort.map({ net($0.id) }) else { return [] }
        var chain: [Component] = []
        while let dev = parentDevice[cur] {
            if dev.kind.isBreaker { chain.append(dev) }
            guard let up = parentNet[cur] else { break }
            cur = up
        }
        return chain
    }

    /// სელექტიურობის გაფრთხილებები (ზედა ავტომატი დაბლას ≥1.6×).
    public func selectivityIssues(_ board: Board) -> [Recommender.Recommendation] {
        var recs: [Recommender.Recommendation] = []
        var seen = Set<String>()
        for load in board.components where load.kind.isLoad {
            let chain = protectiveChain(board, loadID: load.id)
            guard chain.count >= 2 else { continue }
            for i in 0..<(chain.count - 1) {
                let down = chain[i], up = chain[i + 1]
                guard let dr = down.ratingA, let ur = up.ratingA else { continue }
                if !Selectivity.isSelective(upstream: ur, downstream: dr) {
                    let key = up.id + "|" + down.id
                    if seen.contains(key) { continue }
                    seen.insert(key)
                    recs.append(Recommender.Recommendation(
                        severity: .warning,
                        message: "სელექტიურობა: \(up.name) (\(Int(ur))A) ↔ \(down.name) (\(Int(dr))A) — ზედა ავტომატი დაბლას მინიმუმ 1.6×-ით უნდა აღემატებოდეს, რომ მხოლოდ დაზიანებული ხაზი გაითიშოს.",
                        componentIDs: [up.id, down.id]))
                }
            }
        }
        return recs
    }
}
